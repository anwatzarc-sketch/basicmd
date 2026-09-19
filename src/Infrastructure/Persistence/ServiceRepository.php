<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\MedicalService;

final class ServiceRepository
{
    use CrudOperations;

    public function __construct(private readonly Database $db)
    {
    }

    protected function table(): string
    {
        return 'services';
    }

    /**
     * Active services for the public catalogue, optionally filtered.
     *
     * @return list<MedicalService>
     */
    public function publicList(?string $category = null, ?string $search = null): array
    {
        $where  = ["status = 'active'", 'deleted_at IS NULL'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[]            = 'category = :category';
            $params['category'] = $category;
        }

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(
                ['ser_name', 'description', 'name_am', 'description_am'],
                $search,
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM services WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, ser_name ASC',
            $params,
        );

        return array_map(MedicalService::fromRow(...), $rows);
    }

    /** @return list<MedicalService> */
    public function featured(int $limit = 6): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM services
             WHERE status = 'active' AND is_featured = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC LIMIT :limit",
            ['limit' => $limit],
        );

        return array_map(MedicalService::fromRow(...), $rows);
    }

    /** @return list<MedicalService> */
    public function adminList(?string $search = null, ?string $status = null): array
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(['ser_name', 'description'], $search);

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        if ($status !== null && $status !== '') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM services WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, ser_name ASC',
            $params,
        );

        return array_map(MedicalService::fromRow(...), $rows);
    }

    public function findById(int $id): ?MedicalService
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM services WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : MedicalService::fromRow($row);
    }

    public function findBySlug(string $slug): ?MedicalService
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM services WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug],
        );

        return $row === null ? null : MedicalService::fromRow($row);
    }

    /** id => name, for the booking form. @return array<int, string> */
    public function options(): array
    {
        return $this->db->fetchPairs(
            "SELECT id, ser_name FROM services
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY sort_order ASC, ser_name ASC"
        );
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

    public function countActive(): int
    {
        return $this->db->fetchInt("SELECT COUNT(*) FROM services WHERE status = 'active' AND deleted_at IS NULL");
    }

    /** @return array<string, int> category => count */
    public function categoryCounts(): array
    {
        return array_map('intval', $this->db->fetchPairs(
            "SELECT category, COUNT(*) FROM services
             WHERE status = 'active' AND deleted_at IS NULL
             GROUP BY category"
        ));
    }
}
