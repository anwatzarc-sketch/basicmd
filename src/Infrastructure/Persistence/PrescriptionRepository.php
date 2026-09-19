<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\EPrescription;
use MediCareMini\Domain\Repository\PrescriptionRepositoryInterface;

final class PrescriptionRepository implements PrescriptionRepositoryInterface
{
    private const string SELECT_BASE = '
        SELECT p.*, u.full_name AS prescriber_name
        FROM e_prescriptions p
        JOIN users u ON u.id = p.prescriber_id
    ';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?EPrescription
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE p.id = :id', ['id' => $id]);

        return $row === null ? null : EPrescription::fromRow($row);
    }

    /** @return list<EPrescription> */
    public function forEncounter(int $encounterId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE p.encounter_id = :eid ORDER BY p.created_at ASC',
            ['eid' => $encounterId],
        );

        return array_map(EPrescription::fromRow(...), $rows);
    }

    /** @return list<EPrescription> */
    public function forPatient(int $patientId, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . '
             JOIN encounters e ON e.id = p.encounter_id
             WHERE e.patient_id = :pid
             ORDER BY p.created_at DESC
             LIMIT :limit',
            ['pid' => $patientId, 'limit' => $limit],
        );

        return array_map(EPrescription::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO e_prescriptions (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    public function markDispensed(int $id): bool
    {
        return $this->db->execute(
            'UPDATE e_prescriptions SET is_dispensed = 1 WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }
}
