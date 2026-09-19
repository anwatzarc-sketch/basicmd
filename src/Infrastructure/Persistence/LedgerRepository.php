<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\LedgerEntry;
use MediCareMini\Domain\Repository\LedgerRepositoryInterface;
use MediCareMini\Domain\ValueObject\Balance;

final class LedgerRepository implements LedgerRepositoryInterface
{
    private const string SELECT_BASE = '
        SELECT l.*, u.full_name AS accountant_name
        FROM consumption_ledger l
        JOIN users u ON u.id = l.accountant_id
    ';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?LedgerEntry
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE l.id = :id', ['id' => $id]);

        return $row === null ? null : LedgerEntry::fromRow($row);
    }

    /** @return list<LedgerEntry> */
    public function forEncounter(int $encounterId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE l.encounter_id = :eid ORDER BY l.created_at ASC, l.id ASC',
            ['eid' => $encounterId],
        );

        return array_map(LedgerEntry::fromRow(...), $rows);
    }

    public function sumForEncounter(int $encounterId): Balance
    {
        $sum = $this->db->fetchValue(
            'SELECT COALESCE(SUM(total_cost), 0) FROM consumption_ledger WHERE encounter_id = :eid',
            ['eid' => $encounterId],
        );

        return Balance::fromDatabase($sum);
    }

    public function sumForPatient(int $patientId): Balance
    {
        $sum = $this->db->fetchValue(
            'SELECT COALESCE(SUM(l.total_cost), 0)
             FROM consumption_ledger l
             JOIN encounters e ON e.id = l.encounter_id
             WHERE e.patient_id = :pid',
            ['pid' => $patientId],
        );

        return Balance::fromDatabase($sum);
    }

    /** @return list<LedgerEntry> */
    public function forPatient(int $patientId, int $limit = 200): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . '
             JOIN encounters e ON e.id = l.encounter_id
             WHERE e.patient_id = :pid
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit',
            ['pid' => $patientId, 'limit' => $limit],
        );

        return array_map(LedgerEntry::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO consumption_ledger (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }
}
