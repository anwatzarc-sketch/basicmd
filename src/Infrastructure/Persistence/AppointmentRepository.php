<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\Appointment;
use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\TimeSlot;
use Aster\Domain\Exception\BookingException;
use Aster\Domain\ValueObject\BookingReference;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Appointment persistence, including the overbooking guarantee.
 *
 * ---------------------------------------------------------------------
 *  How double-booking is actually prevented
 * ---------------------------------------------------------------------
 * A naive "count, then insert" is a textbook race: two requests both read
 * 3-of-4 taken and both insert, producing 5. Three mechanisms close it:
 *
 *  1. SELECT ... FOR UPDATE on the doctor row serialises every booking for
 *     that doctor. Concurrent requests for the same doctor queue behind the
 *     lock; requests for different doctors never contend.
 *  2. The capacity count runs INSIDE that lock, so the number read is still
 *     true at the moment of insert.
 *  3. A unique index (doctor_id, date, slot, phone) is the backstop. Even if
 *     the application logic were bypassed entirely, the database refuses a
 *     duplicate - which also makes a double-clicked submit button harmless.
 *
 * The lock is held for the duration of one INSERT, so contention is
 * negligible even on a busy morning.
 */
final class AppointmentRepository
{
    /** Columns plus the JOINed display labels every read needs. */
    private const string SELECT_BASE = '
        SELECT a.*,
               d.full_name  AS doctor_name,
               s.name       AS service_name,
               p.title      AS package_name,
               u.full_name  AS handler_name
        FROM appointments a
        LEFT JOIN doctors         d ON d.id = a.doctor_id
        LEFT JOIN services        s ON s.id = a.service_id
        LEFT JOIN health_packages p ON p.id = a.package_id
        LEFT JOIN users           u ON u.id = a.handled_by
    ';

    public function __construct(private readonly Database $db)
    {
    }

    // -----------------------------------------------------------------
    //  Booking
    // -----------------------------------------------------------------

    /**
     * Insert a booking, enforcing capacity atomically.
     *
     * @param array<string, mixed> $data column => value
     * @return int the new appointment id
     *
     * @throws BookingException when the slot, the day or the doctor is full.
     */
    public function createWithCapacityCheck(array $data): int
    {
        return $this->db->transaction(function (Database $db) use ($data): int {
            $date = (string) $data['appointment_date'];
            $slot = (string) $data['time_slot'];

            $doctorId = $data['doctor_id'] === null ? null : (int) $data['doctor_id'];

            // "Any available doctor" is resolved to a concrete doctor here,
            // inside the transaction. Leaving it null would make capacity
            // unenforceable and hand the front desk an unassigned pile.
            if ($doctorId === null) {
                $doctorId = $this->pickLeastLoadedDoctor($db, $date, $slot);

                if ($doctorId === null) {
                    throw BookingException::slotFull();
                }

                $data['doctor_id'] = $doctorId;
            }

            $this->assertCapacityAvailable($db, $doctorId, $date, $slot);

            $columns      = array_keys($data);
            $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

            try {
                $db->execute(
                    'INSERT INTO appointments (' . implode(', ', array_map(
                        static fn (string $c): string => '`' . $c . '`',
                        $columns,
                    )) . ') VALUES (' . implode(', ', $placeholders) . ')',
                    $data,
                );
            } catch (PDOException $e) {
                // The unique slot guard fired: this patient already holds
                // this exact slot. Almost always a double-submitted form.
                if (Database::isDuplicateKey($e)) {
                    throw BookingException::duplicateBooking();
                }

                throw $e;
            }

            return $db->lastInsertId();
        });
    }

    /**
     * Take the row lock and verify both capacity ceilings.
     *
     * @throws BookingException
     */
    private function assertCapacityAvailable(Database $db, int $doctorId, string $date, string $slot): void
    {
        // FOR UPDATE: the serialisation point. Everything below is computed
        // while no other transaction can book this doctor.
        $doctor = $db->fetchOne(
            'SELECT id, daily_capacity, slot_capacity, status
             FROM doctors
             WHERE id = :id AND deleted_at IS NULL
             FOR UPDATE',
            ['id' => $doctorId],
        );

        if ($doctor === null) {
            throw BookingException::doctorUnavailable();
        }

        if ((string) $doctor['status'] !== 'active') {
            throw BookingException::doctorUnavailable();
        }

        if ($this->isOnLeave($db, $doctorId, $date)) {
            throw BookingException::doctorUnavailable();
        }

        $occupying = $this->occupyingStatusList();

        $dailyTaken = $db->fetchInt(
            "SELECT COUNT(*) FROM appointments
             WHERE doctor_id = :d AND appointment_date = :date
               AND deleted_at IS NULL AND status IN ({$occupying})",
            ['d' => $doctorId, 'date' => $date],
        );

        if ($dailyTaken >= (int) $doctor['daily_capacity']) {
            throw BookingException::doctorAtDailyCapacity();
        }

        $slotTaken = $db->fetchInt(
            "SELECT COUNT(*) FROM appointments
             WHERE doctor_id = :d AND appointment_date = :date AND time_slot = :slot
               AND deleted_at IS NULL AND status IN ({$occupying})",
            ['d' => $doctorId, 'date' => $date, 'slot' => $slot],
        );

        if ($slotTaken >= (int) $doctor['slot_capacity']) {
            throw BookingException::slotFull();
        }
    }

    /**
     * Choose the bookable doctor with the most headroom in this slot.
     *
     * Spreading load rather than filling the first doctor keeps every
     * clinician's day balanced and leaves the widest choice for later
     * patients who do request someone specific.
     */
    private function pickLeastLoadedDoctor(Database $db, string $date, string $slot): ?int
    {
        $occupying = $this->occupyingStatusList();

        $row = $db->fetchOne(
            "SELECT d.id
             FROM doctors d
             LEFT JOIN appointments a
                    ON a.doctor_id = d.id
                   AND a.appointment_date = :date
                   AND a.time_slot = :slot
                   AND a.deleted_at IS NULL
                   AND a.status IN ({$occupying})
             WHERE d.status = 'active'
               AND d.deleted_at IS NULL
               AND NOT EXISTS (
                     SELECT 1 FROM doctor_time_off t
                     WHERE t.doctor_id = d.id AND :date2 BETWEEN t.starts_on AND t.ends_on
                   )
             GROUP BY d.id, d.slot_capacity
             HAVING COUNT(a.id) < d.slot_capacity
             ORDER BY (d.slot_capacity - COUNT(a.id)) DESC, d.sort_order ASC
             LIMIT 1
             FOR UPDATE",
            ['date' => $date, 'slot' => $slot, 'date2' => $date],
        );

        return $row === null ? null : (int) $row['id'];
    }

    private function isOnLeave(Database $db, int $doctorId, string $date): bool
    {
        return $db->fetchInt(
            'SELECT COUNT(*) FROM doctor_time_off
             WHERE doctor_id = :d AND :date BETWEEN starts_on AND ends_on',
            ['d' => $doctorId, 'date' => $date],
        ) > 0;
    }

    /**
     * Quoted status list for IN (...).
     *
     * Built from the enum rather than hardcoded, so adding a status that
     * occupies a slot cannot silently break capacity counting. The values are
     * enum cases, never user input, so there is nothing to inject.
     */
    private function occupyingStatusList(): string
    {
        $values = array_map(
            static fn (AppointmentStatus $s): string => "'" . $s->value . "'",
            array_filter(
                AppointmentStatus::all(),
                static fn (AppointmentStatus $s): bool => $s->occupiesSlot(),
            ),
        );

        return implode(', ', $values);
    }

    // -----------------------------------------------------------------
    //  Availability matrix
    // -----------------------------------------------------------------

    /**
     * Remaining capacity per slot for a doctor on one date.
     *
     * @return array<string, int> slot value => seats left
     */
    public function slotAvailability(int $doctorId, string $date): array
    {
        $doctor = $this->db->fetchOne(
            'SELECT slot_capacity, daily_capacity FROM doctors WHERE id = :id AND deleted_at IS NULL',
            ['id' => $doctorId],
        );

        if ($doctor === null) {
            return [];
        }

        if ($this->isOnLeave($this->db, $doctorId, $date)) {
            return array_fill_keys(array_map(static fn (TimeSlot $s): string => $s->value, TimeSlot::all()), 0);
        }

        $occupying = $this->occupyingStatusList();

        $taken = $this->db->fetchPairs(
            "SELECT time_slot, COUNT(*) FROM appointments
             WHERE doctor_id = :d AND appointment_date = :date
               AND deleted_at IS NULL AND status IN ({$occupying})
             GROUP BY time_slot",
            ['d' => $doctorId, 'date' => $date],
        );

        $dailyTaken   = array_sum(array_map('intval', $taken));
        $dailyHeadroom = max(0, (int) $doctor['daily_capacity'] - $dailyTaken);

        $availability = [];

        foreach (TimeSlot::all() as $slot) {
            $slotLeft = max(0, (int) $doctor['slot_capacity'] - (int) ($taken[$slot->value] ?? 0));

            // The day's ceiling also caps each individual slot.
            $availability[$slot->value] = min($slotLeft, $dailyHeadroom);
        }

        return $availability;
    }

    /**
     * Availability across every bookable doctor for one date, for the
     * "any available doctor" path and the admin day view.
     *
     * @return array<string, int> slot value => total seats left
     */
    public function aggregateAvailability(string $date): array
    {
        $occupying = $this->occupyingStatusList();

        $rows = $this->db->fetchAll(
            "SELECT d.id, d.slot_capacity, d.daily_capacity,
                    a.time_slot, COUNT(a.id) AS taken
             FROM doctors d
             LEFT JOIN appointments a
                    ON a.doctor_id = d.id
                   AND a.appointment_date = :date
                   AND a.deleted_at IS NULL
                   AND a.status IN ({$occupying})
             WHERE d.status = 'active'
               AND d.deleted_at IS NULL
               AND NOT EXISTS (
                     SELECT 1 FROM doctor_time_off t
                     WHERE t.doctor_id = d.id AND :date2 BETWEEN t.starts_on AND t.ends_on
                   )
             GROUP BY d.id, d.slot_capacity, d.daily_capacity, a.time_slot",
            ['date' => $date, 'date2' => $date],
        );

        $perDoctor = [];

        foreach ($rows as $row) {
            $doctorId = (int) $row['id'];

            $perDoctor[$doctorId] ??= [
                'slot_capacity'  => (int) $row['slot_capacity'],
                'daily_capacity' => (int) $row['daily_capacity'],
                'slots'          => [],
            ];

            if ($row['time_slot'] !== null) {
                $perDoctor[$doctorId]['slots'][(string) $row['time_slot']] = (int) $row['taken'];
            }
        }

        $totals = array_fill_keys(array_map(static fn (TimeSlot $s): string => $s->value, TimeSlot::all()), 0);

        foreach ($perDoctor as $doctor) {
            $dailyTaken    = array_sum($doctor['slots']);
            $dailyHeadroom = max(0, $doctor['daily_capacity'] - $dailyTaken);

            foreach (TimeSlot::all() as $slot) {
                $slotLeft = max(0, $doctor['slot_capacity'] - ($doctor['slots'][$slot->value] ?? 0));
                $totals[$slot->value] += min($slotLeft, $dailyHeadroom);
            }
        }

        return $totals;
    }

    /**
     * Dates in a range that still have at least one free slot, so the date
     * picker can grey out full days before the patient clicks them.
     *
     * @return array<string, bool> Y-m-d => has availability
     */
    public function availabilityCalendar(string $from, string $to, ?int $doctorId = null): array
    {
        $calendar = [];
        $cursor   = new DateTimeImmutable($from);
        $end      = new DateTimeImmutable($to);

        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');

            $availability = $doctorId !== null
                ? $this->slotAvailability($doctorId, $date)
                : $this->aggregateAvailability($date);

            $calendar[$date] = array_sum($availability) > 0;
            $cursor          = $cursor->modify('+1 day');
        }

        return $calendar;
    }

    // -----------------------------------------------------------------
    //  Reads
    // -----------------------------------------------------------------

    public function findById(int $id): ?Appointment
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE a.id = :id AND a.deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : Appointment::fromRow($row);
    }

    public function findByReference(BookingReference $reference): ?Appointment
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE a.booking_ref = :ref AND a.deleted_at IS NULL',
            ['ref' => $reference->value],
        );

        return $row === null ? null : Appointment::fromRow($row);
    }

    /**
     * Look up a booking for the public "check my appointment" page.
     *
     * Requires BOTH the reference and the phone number. The reference alone
     * is unguessable, but pairing it with the phone means a leaked email
     * forward still does not expose the booking to a stranger.
     */
    public function findForPatient(BookingReference $reference, string $phoneE164): ?Appointment
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE a.booking_ref = :ref AND a.patient_phone = :phone AND a.deleted_at IS NULL',
            ['ref' => $reference->value, 'phone' => $phoneE164],
        );

        return $row === null ? null : Appointment::fromRow($row);
    }

    /**
     * Filtered, paginated listing for the admin queue.
     *
     * @param array{
     *   status?: string, payment_status?: string, doctor_id?: int, date?: string,
     *   date_from?: string, date_to?: string, search?: string, queue_tier?: string,
     *   source?: string
     * } $filters
     * @return list<Appointment>
     */
    public function search(array $filters, int $limit = 25, int $offset = 0, string $sort = 'date_desc'): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        // Allow-list, because an ORDER BY clause cannot be a bound parameter.
        $orderBy = match ($sort) {
            'date_asc'    => 'a.appointment_date ASC, a.time_slot ASC',
            'created_desc'=> 'a.created_at DESC',
            'created_asc' => 'a.created_at ASC',
            // Express first within a day, which is what the surcharge buys.
            'queue'       => "a.appointment_date ASC, a.time_slot ASC, a.queue_tier = 'express' DESC, a.created_at ASC",
            default       => 'a.appointment_date DESC, a.time_slot DESC',
        };

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where . " ORDER BY {$orderBy} LIMIT :limit OFFSET :offset",
            $params,
        );

        return array_map(Appointment::fromRow(...), $rows);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildFilters($filters);

        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM appointments a
             LEFT JOIN doctors d ON d.id = a.doctor_id' . $where,
            $params,
        );
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'a.status = :status';
            $params['status']  = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $where[]          = 'a.payment_status = :pstatus';
            $params['pstatus'] = $filters['payment_status'];
        }

        if (!empty($filters['doctor_id'])) {
            $where[]             = 'a.doctor_id = :doctor';
            $params['doctor']    = (int) $filters['doctor_id'];
        }

        if (!empty($filters['queue_tier'])) {
            $where[]         = 'a.queue_tier = :tier';
            $params['tier']  = $filters['queue_tier'];
        }

        if (!empty($filters['source'])) {
            $where[]          = 'a.source = :source';
            $params['source'] = $filters['source'];
        }

        if (!empty($filters['date'])) {
            $where[]         = 'a.appointment_date = :date';
            $params['date']  = $filters['date'];
        }

        if (!empty($filters['date_from'])) {
            $where[]           = 'a.appointment_date >= :dfrom';
            $params['dfrom']   = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]         = 'a.appointment_date <= :dto';
            $params['dto']   = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            // LIKE rather than FULLTEXT: staff search partial phone numbers
            // and reference fragments, which a word-based index cannot match.
            [$clause, $searchParams] = Database::searchClause(
                ['a.patient_name', 'a.patient_phone', 'a.booking_ref', 'a.patient_email'],
                (string) $filters['search'],
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * A doctor's own queue for a date - the clinical day view.
     *
     * @return list<Appointment>
     */
    public function dayQueueForDoctor(int $doctorId, string $date): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . "
             WHERE a.doctor_id = :d
               AND a.appointment_date = :date
               AND a.deleted_at IS NULL
               AND a.status IN ('pending', 'confirmed', 'completed')
             ORDER BY a.time_slot ASC, a.queue_tier = 'express' DESC, a.created_at ASC",
            ['d' => $doctorId, 'date' => $date],
        );

        return array_map(Appointment::fromRow(...), $rows);
    }

    /** @return list<Appointment> */
    public function recent(int $limit = 5): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE a.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT :limit',
            ['limit' => $limit],
        );

        return array_map(Appointment::fromRow(...), $rows);
    }

    // -----------------------------------------------------------------
    //  Writes
    // -----------------------------------------------------------------

    /**
     * Apply a lifecycle transition, re-checking it under a row lock.
     *
     * The state machine is validated in the service layer too, but two admins
     * clicking "Confirm" at once would both pass that check. Re-reading the
     * status FOR UPDATE makes the second one fail as it should.
     *
     * @throws BookingException on an illegal transition
     */
    public function transitionStatus(
        int $id,
        AppointmentStatus $target,
        ?int $actorId,
        ?string $reason = null,
    ): AppointmentStatus {
        return $this->db->transaction(function (Database $db) use ($id, $target, $actorId, $reason): AppointmentStatus {
            $row = $db->fetchOne(
                'SELECT status FROM appointments WHERE id = :id AND deleted_at IS NULL FOR UPDATE',
                ['id' => $id],
            );

            if ($row === null) {
                throw BookingException::notFound();
            }

            $current = AppointmentStatus::from((string) $row['status']);

            if ($current === $target) {
                return $current; // Idempotent: a double-click is not an error.
            }

            if (!$current->canTransitionTo($target)) {
                throw BookingException::invalidTransition($current, $target);
            }

            // Stamp the timestamp column matching the destination state.
            $timestampColumn = match ($target) {
                AppointmentStatus::CONFIRMED => 'confirmed_at',
                AppointmentStatus::COMPLETED => 'completed_at',
                AppointmentStatus::CANCELLED => 'cancelled_at',
                default                      => null,
            };

            $sql    = 'UPDATE appointments SET status = :status, handled_by = :actor';
            $params = ['status' => $target->value, 'actor' => $actorId, 'id' => $id];

            if ($timestampColumn !== null) {
                $sql .= ", {$timestampColumn} = UTC_TIMESTAMP()";
            }

            if ($target === AppointmentStatus::CANCELLED) {
                $sql             .= ', cancel_reason = :reason';
                $params['reason'] = $reason;
            }

            $db->execute($sql . ' WHERE id = :id', $params);

            return $current;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => '`' . $c . '` = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        return $this->db->execute(
            "UPDATE appointments SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
            $data,
        ) > 0;
    }

    /**
     * Recalculate amount_paid and payment_status from verified proofs.
     *
     * Derived rather than incremented, so a rejected-then-re-verified slip
     * cannot leave the running total wrong. The one source of truth is the
     * set of verified payment rows.
     */
    public function recalculatePayment(int $appointmentId): void
    {
        $this->db->execute(
            "UPDATE appointments a
             SET a.amount_paid = COALESCE((
                     SELECT SUM(p.amount) FROM payments p
                     WHERE p.appointment_id = a.id AND p.status = 'verified'
                 ), 0),
                 a.payment_status = CASE
                     WHEN a.payment_status IN ('refunded', 'waived') THEN a.payment_status
                     WHEN a.total_amount <= 0 THEN 'waived'
                     WHEN COALESCE((SELECT SUM(p.amount) FROM payments p
                           WHERE p.appointment_id = a.id AND p.status = 'verified'), 0) >= a.total_amount
                         THEN 'paid'
                     WHEN COALESCE((SELECT SUM(p.amount) FROM payments p
                           WHERE p.appointment_id = a.id AND p.status = 'verified'), 0) > 0
                         THEN 'deposit_paid'
                     WHEN EXISTS (SELECT 1 FROM payments p
                           WHERE p.appointment_id = a.id AND p.status = 'submitted')
                         THEN 'awaiting_verification'
                     ELSE 'unpaid'
                 END
             WHERE a.id = :id",
            ['id' => $appointmentId],
        );
    }

    /** Soft delete - the row stays for the audit trail and revenue history. */
    public function softDelete(int $id): bool
    {
        return $this->db->execute(
            'UPDATE appointments SET deleted_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }

    public function referenceExists(string $reference): bool
    {
        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM appointments WHERE booking_ref = :r',
            ['r' => $reference],
        ) > 0;
    }

    // -----------------------------------------------------------------
    //  Notification queries (used by the cron workers)
    // -----------------------------------------------------------------

    /**
     * Confirmed appointments starting inside the reminder window that have
     * not been reminded yet.
     *
     * @return list<Appointment>
     */
    public function dueForReminder(int $leadHours, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . "
             WHERE a.deleted_at IS NULL
               AND a.status = 'confirmed'
               AND a.reminder_sent_at IS NULL
               AND a.patient_email IS NOT NULL
               AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
               AND TIMESTAMP(a.appointment_date, SUBSTRING_INDEX(a.time_slot, '-', 1))
                   BETWEEN UTC_TIMESTAMP() AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL :hours HOUR)
             ORDER BY a.appointment_date ASC
             LIMIT :limit",
            [
                'days'  => (int) ceil($leadHours / 24) + 1,
                'hours' => $leadHours,
                'limit' => $limit,
            ],
        );

        return array_map(Appointment::fromRow(...), $rows);
    }

    /**
     * Completed visits ready for a follow-up message.
     *
     * @return list<Appointment>
     */
    public function dueForFollowup(int $delayHours, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . "
             WHERE a.deleted_at IS NULL
               AND a.status = 'completed'
               AND a.followup_sent_at IS NULL
               AND a.patient_email IS NOT NULL
               AND a.completed_at IS NOT NULL
               AND a.completed_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL :hours HOUR)
               AND a.completed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
             ORDER BY a.completed_at ASC
             LIMIT :limit",
            ['hours' => $delayHours, 'limit' => $limit],
        );

        return array_map(Appointment::fromRow(...), $rows);
    }

    public function markReminderSent(int $id): void
    {
        $this->db->execute(
            'UPDATE appointments SET reminder_sent_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function markFollowupSent(int $id): void
    {
        $this->db->execute(
            'UPDATE appointments SET followup_sent_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Mark past confirmed appointments as no-shows.
     *
     * Runs nightly. Without it, yesterday's unattended bookings sit in
     * "confirmed" forever and quietly distort both the dashboard and the
     * capacity counts for any rescheduling.
     */
    public function autoMarkNoShows(int $graceHours = 6): int
    {
        return $this->db->execute(
            "UPDATE appointments
             SET status = 'no_show'
             WHERE status = 'confirmed'
               AND deleted_at IS NULL
               AND TIMESTAMP(appointment_date, SUBSTRING_INDEX(time_slot, '-', -1))
                   < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :grace HOUR)",
            ['grace' => $graceHours],
        );
    }
}
