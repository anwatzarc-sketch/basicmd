<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\ReceivablePayment;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\ValueObject\Balance;
use Aster\Domain\ValueObject\ReceiptId;

final class ReceivablePaymentRepository implements ReceivablePaymentRepositoryInterface
{
    private const string SELECT_BASE = '
        SELECT p.*, u.full_name AS accountant_name
        FROM receivable_payments p
        JOIN users u ON u.id = p.accountant_id
    ';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?ReceivablePayment
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE p.id = :id', ['id' => $id]);

        return $row === null ? null : ReceivablePayment::fromRow($row);
    }

    public function findByReceiptId(ReceiptId $receiptId): ?ReceivablePayment
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE p.receipt_id = :rid',
            ['rid' => $receiptId->value],
        );

        return $row === null ? null : ReceivablePayment::fromRow($row);
    }

    /** @return list<ReceivablePayment> */
    public function forEncounter(int $encounterId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE p.encounter_id = :eid ORDER BY p.created_at ASC, p.id ASC',
            ['eid' => $encounterId],
        );

        return array_map(ReceivablePayment::fromRow(...), $rows);
    }

    public function sumForEncounter(int $encounterId): Balance
    {
        $sum = $this->db->fetchValue(
            'SELECT COALESCE(SUM(amount_paid), 0) FROM receivable_payments WHERE encounter_id = :eid',
            ['eid' => $encounterId],
        );

        return Balance::fromDatabase($sum);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO receivable_payments (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }
}
