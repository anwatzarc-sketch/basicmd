<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\ValueObject\Money;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\EncounterRepository;
use Aster\Infrastructure\Persistence\InquiryRepository;
use Aster\Infrastructure\Persistence\PaymentRepository;
use Aster\Infrastructure\Support\Config;
use DateTimeImmutable;

/**
 * Dashboard analytics.
 *
 * Every figure here answers a question the clinic actually asks:
 *   - Is today staffed correctly?      -> today's load per doctor
 *   - Is money being collected?        -> verified revenue, outstanding
 *   - Is the website earning its keep? -> web bookings vs. phone/walk-in
 *   - Are we losing slots?             -> no-show rate
 *
 * Aggregates are computed in SQL rather than by loading rows into PHP: a busy
 * month is tens of thousands of appointments, and summing those in the
 * application would make the dashboard the slowest page in the system.
 */
final readonly class DashboardService
{
    public function __construct(
        private Database $db,
        private AppointmentRepository $appointments,
        private PaymentRepository $payments,
        private DoctorRepository $doctors,
        private InquiryRepository $inquiries,
        private EncounterRepository $encounters,
        private Config $config,
    ) {
    }

    /** Every non-terminal EncounterStatus, as a SQL IN() list - see EncounterRepository::active(). */
    private function activeStatusList(): string
    {
        return implode(', ', array_map(
            static fn (EncounterStatus $s): string => "'" . $s->value . "'",
            array_filter(EncounterStatus::all(), static fn (EncounterStatus $s): bool => !$s->isTerminal()),
        ));
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', $this->config->timezone))->format('Y-m-d');
    }

    /**
     * Headline counters for the metric cards.
     *
     * @return array<string, int|string>
     */
    public function headline(): array
    {
        $today = $this->today();

        return [
            'appointments_today'   => $this->db->fetchInt(
                "SELECT COUNT(*) FROM appointments
                 WHERE appointment_date = :d AND deleted_at IS NULL
                   AND status IN ('pending','confirmed','completed')",
                ['d' => $today],
            ),
            'pending_appointments' => $this->db->fetchInt(
                "SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND deleted_at IS NULL"
            ),
            'new_today'            => $this->db->fetchInt(
                'SELECT COUNT(*) FROM appointments WHERE DATE(created_at) = :d AND deleted_at IS NULL',
                ['d' => $today],
            ),
            'upcoming_week'        => $this->db->fetchInt(
                "SELECT COUNT(*) FROM appointments
                 WHERE appointment_date BETWEEN :d AND DATE_ADD(:d2, INTERVAL 7 DAY)
                   AND status IN ('pending','confirmed') AND deleted_at IS NULL",
                ['d' => $today, 'd2' => $today],
            ),
            'pending_payments'     => $this->payments->countPendingReview(),
            'unread_inquiries'     => $this->inquiries->countUnread(),
            'active_doctors'       => $this->doctors->countActive(),
        ];
    }

    /**
     * Financial summary for a period.
     *
     * "Collected" counts only verified payments - an uploaded slip is a
     * claim, not money. "Outstanding" is what has been promised by confirmed
     * bookings but not yet settled, which is the figure Finance chases.
     *
     * @return array{collected:Money, outstanding:Money, awaiting_verification:Money, expected:Money}
     */
    public function financials(string $from, string $to): array
    {
        $collected = Money::fromMinor($this->payments->verifiedRevenueBetween($from, $to));

        $outstanding = Money::fromDatabase($this->db->fetchValue(
            "SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM appointments
             WHERE deleted_at IS NULL
               AND status IN ('pending','confirmed','completed')
               AND payment_status NOT IN ('refunded','waived')
               AND total_amount > amount_paid
               AND appointment_date BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to],
        ));

        $awaiting = Money::fromDatabase($this->db->fetchValue(
            "SELECT COALESCE(SUM(p.amount), 0) FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             WHERE p.status = 'submitted' AND a.deleted_at IS NULL",
        ));

        $expected = Money::fromDatabase($this->db->fetchValue(
            "SELECT COALESCE(SUM(total_amount), 0) FROM appointments
             WHERE deleted_at IS NULL
               AND status IN ('pending','confirmed','completed')
               AND appointment_date BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to],
        ));

        return [
            'collected'             => $collected,
            'outstanding'           => $outstanding,
            'awaiting_verification' => $awaiting,
            'expected'              => $expected,
        ];
    }

    /**
     * Booking mix by channel, which is how the website's contribution is
     * measured against phone and walk-in.
     *
     * @return array{counts: array<string,int>, web_share: float}
     */
    public function acquisitionMix(string $from, string $to): array
    {
        $counts = array_map('intval', $this->db->fetchPairs(
            'SELECT source, COUNT(*) FROM appointments
             WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN :from AND :to
             GROUP BY source',
            ['from' => $from, 'to' => $to],
        ));

        $total = array_sum($counts);

        return [
            'counts'    => $counts,
            'web_share' => $total > 0 ? round((($counts['web'] ?? 0) / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Completion and no-show rates.
     *
     * The no-show rate is the number the reminder engine exists to move, so
     * it is surfaced prominently rather than buried in a report.
     *
     * @return array{total:int, completed:int, no_show:int, cancelled:int, no_show_rate:float, completion_rate:float}
     */
    public function attendance(string $from, string $to): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'completed') AS completed,
                SUM(status = 'no_show')   AS no_show,
                SUM(status = 'cancelled') AS cancelled
             FROM appointments
             WHERE deleted_at IS NULL
               AND appointment_date BETWEEN :from AND :to
               AND appointment_date <= CURDATE()",
            ['from' => $from, 'to' => $to],
        ) ?? [];

        $total     = (int) ($row['total'] ?? 0);
        $completed = (int) ($row['completed'] ?? 0);
        $noShow    = (int) ($row['no_show'] ?? 0);

        // Denominator is appointments the patient was expected to attend -
        // a cancelled slot the clinic could refill is not a no-show.
        $attendable = $completed + $noShow;

        return [
            'total'           => $total,
            'completed'       => $completed,
            'no_show'         => $noShow,
            'cancelled'       => (int) ($row['cancelled'] ?? 0),
            'no_show_rate'    => $attendable > 0 ? round(($noShow / $attendable) * 100, 1) : 0.0,
            'completion_rate' => $attendable > 0 ? round(($completed / $attendable) * 100, 1) : 0.0,
        ];
    }

    /**
     * Today's load per doctor, with a utilisation percentage.
     *
     * @return list<array{id:int, full_name:string, booked:int, daily_capacity:int, utilisation:float}>
     */
    public function doctorLoad(?string $date = null): array
    {
        $rows = $this->doctors->dailyLoad($date ?? $this->today());

        return array_map(static function (array $row): array {
            $capacity = max(1, (int) $row['daily_capacity']);
            $booked   = (int) $row['booked'];

            return [
                'id'             => (int) $row['id'],
                'full_name'      => (string) $row['full_name'],
                'booked'         => $booked,
                'daily_capacity' => (int) $row['daily_capacity'],
                'utilisation'    => round(min(100, ($booked / $capacity) * 100), 1),
            ];
        }, $rows);
    }

    /**
     * Daily booking counts for the trend sparkline.
     *
     * Gaps are filled with zeros: a chart that silently skips quiet days
     * misrepresents the trend as flatter than it is.
     *
     * @return array<string, int> Y-m-d => count
     */
    public function bookingTrend(int $days = 30): array
    {
        $tz    = $this->config->timezone;
        $end   = new DateTimeImmutable('today', $tz);
        $start = $end->modify('-' . ($days - 1) . ' days');

        $raw = array_map('intval', $this->db->fetchPairs(
            'SELECT DATE(created_at) AS d, COUNT(*) FROM appointments
             WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN :from AND :to
             GROUP BY DATE(created_at)',
            ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')],
        ));

        $series = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $key          = $cursor->format('Y-m-d');
            $series[$key] = $raw[$key] ?? 0;
            $cursor       = $cursor->modify('+1 day');
        }

        return $series;
    }

    /**
     * Daily verified revenue, zero-filled to match bookingTrend().
     *
     * @return array<string, float>
     */
    public function revenueTrend(int $days = 30): array
    {
        $tz    = $this->config->timezone;
        $end   = new DateTimeImmutable('today', $tz);
        $start = $end->modify('-' . ($days - 1) . ' days');

        $raw = $this->payments->dailyRevenue($start->format('Y-m-d'), $end->format('Y-m-d'));

        $series = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $key          = $cursor->format('Y-m-d');
            $series[$key] = $raw[$key] ?? 0.0;
            $cursor       = $cursor->modify('+1 day');
        }

        return $series;
    }

    /**
     * Most-booked services, for deciding where to add capacity.
     *
     * @return list<array{name:string, bookings:int, revenue:string}>
     */
    public function topServices(string $from, string $to, int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT s.name,
                    COUNT(a.id) AS bookings,
                    COALESCE(SUM(a.amount_paid), 0) AS revenue
             FROM services s
             INNER JOIN appointments a
                     ON a.service_id = s.id
                    AND a.deleted_at IS NULL
                    AND a.appointment_date BETWEEN :from AND :to
                    AND a.status IN ('pending','confirmed','completed')
             WHERE s.deleted_at IS NULL
             GROUP BY s.id, s.name
             ORDER BY bookings DESC
             LIMIT :limit",
            ['from' => $from, 'to' => $to, 'limit' => $limit],
        );
    }

    /**
     * Express-queue uptake - the surcharge's contribution.
     *
     * @return array{count:int, revenue:Money, share:float}
     */
    public function expressUptake(string $from, string $to): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                SUM(queue_tier = 'express') AS express_count,
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN queue_tier = 'express' THEN surcharge_amount ELSE 0 END), 0) AS surcharge_total
             FROM appointments
             WHERE deleted_at IS NULL
               AND status IN ('pending','confirmed','completed')
               AND appointment_date BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to],
        ) ?? [];

        $express = (int) ($row['express_count'] ?? 0);
        $total   = (int) ($row['total'] ?? 0);

        return [
            'count'   => $express,
            'revenue' => Money::fromDatabase($row['surcharge_total'] ?? 0),
            'share'   => $total > 0 ? round(($express / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Settlement split by payment channel, for bank reconciliation.
     *
     * @return list<array<string, mixed>>
     */
    public function settlementByMethod(string $from, string $to): array
    {
        return $this->payments->settlementByMethod($from, $to);
    }

    /** @return list<\Aster\Domain\Entity\Appointment> */
    public function recentAppointments(int $limit = 6): array
    {
        return $this->appointments->recent($limit);
    }

    /** @return list<\Aster\Domain\Entity\ContactInquiry> */
    public function recentInquiries(int $limit = 5): array
    {
        return $this->inquiries->recent($limit);
    }

    /** Today's queue for one doctor, used by the clinical dashboard. */
    public function doctorDayQueue(int $doctorId, ?string $date = null): array
    {
        return $this->appointments->dayQueueForDoctor($doctorId, $date ?? $this->today());
    }

    // -----------------------------------------------------------------
    //  Phase II - clinical operations, MPI, and the financial ledger.
    //
    //  Same rule as the panels above: every figure answers a question
    //  someone actually asks at a glance - "how full are we right now",
    //  "is anyone stuck waiting on financial clearance", "how much is
    //  currently owed across the building" - not a dump of table counts.
    // -----------------------------------------------------------------

    /**
     * Right-now snapshot of who is in the building and where.
     *
     * @return array{active_encounters:int, admitted_today:int, discharged_today:int, beds_occupied:int, beds_total:int, pending_clearance:int}
     */
    public function clinicalHeadline(): array
    {
        $today = $this->today();
        $active = $this->activeStatusList();

        $beds = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(is_occupied) AS occupied
             FROM ward_locations WHERE is_transient = 0',
        ) ?? [];

        return [
            'active_encounters'  => $this->db->fetchInt("SELECT COUNT(*) FROM encounters WHERE status IN ({$active})"),
            'admitted_today'     => $this->db->fetchInt(
                'SELECT COUNT(*) FROM encounters WHERE DATE(admitted_at) = :d',
                ['d' => $today],
            ),
            'discharged_today'   => $this->db->fetchInt(
                'SELECT COUNT(*) FROM encounters WHERE DATE(discharged_at) = :d',
                ['d' => $today],
            ),
            'beds_occupied'      => (int) ($beds['occupied'] ?? 0),
            'beds_total'         => (int) ($beds['total'] ?? 0),
            // A financially PENDING encounter that is still open is one
            // discharge is currently blocked on (BillingService's gate) -
            // the figure the front desk and accounts both watch.
            'pending_clearance'  => $this->db->fetchInt(
                "SELECT COUNT(*) FROM encounters
                 WHERE status IN ({$active}) AND financial_clearance_status = 'PENDING'",
            ),
        ];
    }

    /**
     * Active-encounter mix by visit type, for the same reason
     * acquisitionMix() breaks bookings down by channel.
     *
     * @return array<string,int>
     */
    public function encountersByVisitType(): array
    {
        $active = $this->activeStatusList();

        return array_map('intval', $this->db->fetchPairs(
            "SELECT visit_type, COUNT(*) FROM encounters WHERE status IN ({$active}) GROUP BY visit_type",
        ));
    }

    /**
     * Master Patient Index growth and portal adoption.
     *
     * @return array{total_patients:int, new_this_period:int, portal_accounts:int}
     */
    public function mpiHeadline(string $from, string $to): array
    {
        return [
            'total_patients'   => $this->db->fetchInt('SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL'),
            'new_this_period'  => $this->db->fetchInt(
                'SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL AND DATE(created_at) BETWEEN :from AND :to',
                ['from' => $from, 'to' => $to],
            ),
            'portal_accounts'  => $this->db->fetchInt('SELECT COUNT(*) FROM patient_accounts WHERE is_active = 1'),
        ];
    }

    /**
     * Consumption-ledger and receivable-payment activity for a period,
     * plus the system-wide unsettled balance right now.
     *
     * Charges and payments are summed directly rather than through
     * BillingService::calculateReceivableBalance(), which is scoped to
     * one encounter at a time - a dashboard total needs one aggregate
     * query across every open encounter, not N calls to that service.
     *
     * @return array{charges_posted:Money, payments_posted:Money, outstanding:Money, open_balances:int}
     */
    public function ledgerSnapshot(string $from, string $to): array
    {
        $charges = Money::fromDatabase($this->db->fetchValue(
            'SELECT COALESCE(SUM(total_cost), 0) FROM consumption_ledger WHERE DATE(created_at) BETWEEN :from AND :to',
            ['from' => $from, 'to' => $to],
        ));

        $paid = Money::fromDatabase($this->db->fetchValue(
            'SELECT COALESCE(SUM(amount_paid), 0) FROM receivable_payments WHERE DATE(created_at) BETWEEN :from AND :to',
            ['from' => $from, 'to' => $to],
        ));

        $active = $this->activeStatusList();

        $balanceRow = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(l.total_cost), 0) AS charged,
                COALESCE(SUM(p.amount_paid), 0) AS paid,
                COUNT(DISTINCT e.id) AS open_count
             FROM encounters e
             LEFT JOIN (SELECT encounter_id, SUM(total_cost) AS total_cost FROM consumption_ledger GROUP BY encounter_id) l
                    ON l.encounter_id = e.id
             LEFT JOIN (SELECT encounter_id, SUM(amount_paid) AS amount_paid FROM receivable_payments GROUP BY encounter_id) p
                    ON p.encounter_id = e.id
             WHERE e.status IN ({$active})
               AND COALESCE(l.total_cost, 0) - COALESCE(p.amount_paid, 0) > 0",
        ) ?? [];

        $outstanding = Money::fromDatabase(
            (string) ((float) ($balanceRow['charged'] ?? 0) - (float) ($balanceRow['paid'] ?? 0))
        );

        return [
            'charges_posted'  => $charges,
            'payments_posted' => $paid,
            'outstanding'     => $outstanding,
            'open_balances'   => (int) ($balanceRow['open_count'] ?? 0),
        ];
    }

    /** @return list<\Aster\Domain\Entity\Encounter> */
    public function recentEncounters(int $limit = 6): array
    {
        return $this->encounters->active($limit);
    }
}
