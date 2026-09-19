<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\Facility;

final class FacilityRepository
{
    use CrudOperations;

    public function __construct(private readonly Database $db)
    {
    }

    protected function table(): string
    {
        return 'facilities';
    }

    /** @return list<Facility> */
    public function publicList(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM facilities
             WHERE is_public = 1 AND status <> 'offline' AND deleted_at IS NULL
             ORDER BY sort_order ASC, fac_name ASC"
        );

        return array_map(Facility::fromRow(...), $rows);
    }

    /** @return list<Facility> */
    public function adminList(?string $status = null): array
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM facilities WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, fac_name ASC',
            $params,
        );

        return array_map(Facility::fromRow(...), $rows);
    }

    public function findById(int $id): ?Facility
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM facilities WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : Facility::fromRow($row);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insertRow($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        return $this->updateRow($id, $data);
    }

    /** @return array<string, int> status => count, for the operations panel */
    public function statusCounts(): array
    {
        return array_map('intval', $this->db->fetchPairs(
            'SELECT status, COUNT(*) FROM facilities WHERE deleted_at IS NULL GROUP BY status'
        ));
    }
}
