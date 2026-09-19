<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\Payment;
use MediCareMini\Domain\Entity\PaymentMethod;
use MediCareMini\Domain\Enum\ProofStatus;
use MediCareMini\Domain\Exception\BookingException;

/**
 * Proof-of-payment records and the transfer instructions behind them.
 *
 * The verification queue is the money path, so every state change here goes
 * through a locking transaction: two Finance officers opening the same slip
 * must not both be able to approve it and double-credit the booking.
 */
final class PaymentRepository
{
    private const string SELECT_BASE = '
        SELECT p.*,
               m.provider  AS method_label,
               u.full_name AS verifier_name,
               a.booking_ref,
               a.patient_name
        FROM payments p
        LEFT JOIN payment_methods m ON m.id = p.payment_method_id
        LEFT JOIN users           u ON u.id = p.verified_by
        INNER JOIN appointments   a ON a.id = p.appointment_id
    ';

    public function __construct(private readonly Database $db)
    {
    }

    // -----------------------------------------------------------------
    //  Payment methods (transfer instructions)
    // -----------------------------------------------------------------

    /** @return list<PaymentMethod> */
    public function activeMethods(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM payment_methods
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY sort_order ASC, provider ASC"
        );

        return array_map(PaymentMethod::fromRow(...), $rows);
    }

    /** @return list<PaymentMethod> */
    public function allMethods(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM payment_methods WHERE deleted_at IS NULL ORDER BY sort_order ASC, provider ASC'
        );

        return array_map(PaymentMethod::fromRow(...), $rows);
    }

    public function findMethod(int $id): ?PaymentMethod
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM payment_methods WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : PaymentMethod::fromRow($row);
    }

    /** @param array<string, mixed> $data */
    public function createMethod(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO payment_methods (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns))
            . ') VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateMethod(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        return $this->db->execute(
            "UPDATE payment_methods SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
            $data,
        ) > 0;
    }

    public function deleteMethod(int $id): bool
    {
        return $this->db->execute(
            'UPDATE payment_methods SET deleted_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }

    // -----------------------------------------------------------------
    //  Proof submissions
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO payments (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns))
            . ') VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    public function findById(int $id): ?Payment
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE p.id = :id', ['id' => $id]);

        return $row === null ? null : Payment::fromRow($row);
    }

    /** @return list<Payment> */
    public function forAppointment(int $appointmentId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE p.appointment_id = :a ORDER BY p.created_at DESC',
            ['a' => $appointmentId],
        );

        return array_map(Payment::fromRow(...), $rows);
    }

    /**
     * The Finance review queue.
     *
     * @param array{status?:string, search?:string, method_id?:int} $filters
     * @return list<Payment>
     */
    public function search(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where
            // Pending slips first: a patient waiting on verification cannot
            // attend, so this queue is latency-sensitive.
            . " ORDER BY FIELD(p.status, 'submitted', 'awaiting_proof', 'rejected', 'verified'),
                        p.created_at ASC
               LIMIT :limit OFFSET :offset",
            $params,
        );

        return array_map(Payment::fromRow(...), $rows);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildFilters($filters);

        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id' . $where,
            $params,
        );
    }

    /** @return array{string, array<string, mixed>} */
    private function buildFilters(array $filters): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['method_id'])) {
            $where[]          = 'p.payment_method_id = :method';
            $params['method'] = (int) $filters['method_id'];
        }

        if (!empty($filters['search'])) {
            [$clause, $searchParams] = Database::searchClause(
                ['a.booking_ref', 'a.patient_name', 'p.transfer_ref', 'p.payer_name'],
                (string) $filters['search'],
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    public function countPendingReview(): int
    {
        return $this->db->fetchInt(
            "SELECT COUNT(*) FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             WHERE p.status = 'submitted' AND a.deleted_at IS NULL"
        );
    }

    /**
     * Approve a slip.
     *
     * Re-reads the row FOR UPDATE and rejects anything not still in
     * `submitted`, so a second officer clicking Approve on a stale page gets
     * a clear error rather than crediting the booking twice.
     *
     * @throws BookingException
     */
    public function verify(int $paymentId, int $verifierId, ?string $note = null): Payment
    {
        return $this->db->transaction(function (Database $db) use ($paymentId, $verifierId, $note): Payment {
            $row = $db->fetchOne(
                'SELECT id, appointment_id, status FROM payments WHERE id = :id FOR UPDATE',
                ['id' => $paymentId],
            );

            if ($row === null) {
                throw BookingException::notFound();
            }

            $current = ProofStatus::from((string) $row['status']);

            if (!$current->canTransitionTo(ProofStatus::VERIFIED)) {
                throw BookingException::proofAlreadyResolved($current);
            }

            $db->execute(
                "UPDATE payments
                 SET status = 'verified', verified_by = :by, verified_at = UTC_TIMESTAMP(),
                     admin_note = :note, rejection_reason = NULL
                 WHERE id = :id",
                ['by' => $verifierId, 'note' => $note, 'id' => $paymentId],
            );

            return $this->findById($paymentId);
        });
    }

    /**
     * Reject a slip with a reason the patient can act on.
     *
     * @throws BookingException
     */
    public function reject(int $paymentId, int $verifierId, string $reason): Payment
    {
        return $this->db->transaction(function (Database $db) use ($paymentId, $verifierId, $reason): Payment {
            $row = $db->fetchOne(
                'SELECT id, status FROM payments WHERE id = :id FOR UPDATE',
                ['id' => $paymentId],
            );

            if ($row === null) {
                throw BookingException::notFound();
            }

            $current = ProofStatus::from((string) $row['status']);

            if (!$current->canTransitionTo(ProofStatus::REJECTED)) {
                throw BookingException::proofAlreadyResolved($current);
            }

            $db->execute(
                "UPDATE payments
                 SET status = 'rejected', verified_by = :by, verified_at = UTC_TIMESTAMP(),
                     rejection_reason = :reason
                 WHERE id = :id",
                ['by' => $verifierId, 'reason' => mb_substr($reason, 0, 500), 'id' => $paymentId],
            );

            return $this->findById($paymentId);
        });
    }

    /**
     * Find an earlier submission with the same file digest.
     *
     * A slip reused across two bookings is the most common fraud on a manual
     * verification flow; surfacing the collision lets Finance catch it before
     * approving.
     *
     * @return list<array<string, mixed>>
     */
    public function findDuplicateProofs(string $sha256, int $excludePaymentId = 0): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.appointment_id, p.created_at, p.status, a.booking_ref, a.patient_name
             FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             WHERE p.proof_sha256 = :sha AND p.id <> :exclude
             ORDER BY p.created_at ASC',
            ['sha' => $sha256, 'exclude' => $excludePaymentId],
        );
    }

    /** Total verified revenue in a date window, in minor units. */
    public function verifiedRevenueBetween(string $from, string $to): int
    {
        $value = $this->db->fetchValue(
            "SELECT COALESCE(SUM(p.amount), 0) FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             WHERE p.status = 'verified'
               AND a.deleted_at IS NULL
               AND DATE(p.verified_at) BETWEEN :from AND :to",
            ['from' => $from, 'to' => $to],
        );

        return (int) round(((float) $value) * 100);
    }

    /**
     * Daily verified totals for the revenue sparkline.
     *
     * @return array<string, float> Y-m-d => amount
     */
    public function dailyRevenue(string $from, string $to): array
    {
        return array_map('floatval', $this->db->fetchPairs(
            "SELECT DATE(p.verified_at) AS d, SUM(p.amount)
             FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             WHERE p.status = 'verified'
               AND a.deleted_at IS NULL
               AND DATE(p.verified_at) BETWEEN :from AND :to
             GROUP BY DATE(p.verified_at)
             ORDER BY d ASC",
            ['from' => $from, 'to' => $to],
        ));
    }

    /**
     * Settlement split by payment channel, for reconciliation against bank
     * statements.
     *
     * @return list<array{provider:string, channel:string, count:int, total:string}>
     */
    public function settlementByMethod(string $from, string $to): array
    {
        return $this->db->fetchAll(
            "SELECT COALESCE(m.provider, 'Unspecified') AS provider,
                    COALESCE(m.channel, 'bank_transfer') AS channel,
                    COUNT(p.id) AS count,
                    COALESCE(SUM(p.amount), 0) AS total
             FROM payments p
             INNER JOIN appointments a ON a.id = p.appointment_id
             LEFT JOIN payment_methods m ON m.id = p.payment_method_id
             WHERE p.status = 'verified'
               AND a.deleted_at IS NULL
               AND DATE(p.verified_at) BETWEEN :from AND :to
             GROUP BY m.id, m.provider, m.channel
             ORDER BY total DESC",
            ['from' => $from, 'to' => $to],
        );
    }
}
