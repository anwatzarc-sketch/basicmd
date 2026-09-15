<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\HealthPackage;

final class PackageRepository
{
    use CrudOperations;

    public function __construct(private readonly Database $db)
    {
    }

    protected function table(): string
    {
        return 'health_packages';
    }

    /** @return list<HealthPackage> */
    public function publicList(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM health_packages
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY sort_order ASC, price_etb ASC"
        );

        return array_map(HealthPackage::fromRow(...), $rows);
    }

    /** @return list<HealthPackage> */
    public function adminList(?string $status = null): array
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM health_packages WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, price_etb ASC',
            $params,
        );

        return array_map(HealthPackage::fromRow(...), $rows);
    }

    public function findById(int $id): ?HealthPackage
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM health_packages WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : HealthPackage::fromRow($row);
    }

    public function findBySlug(string $slug): ?HealthPackage
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM health_packages WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug],
        );

        return $row === null ? null : HealthPackage::fromRow($row);
    }

    /** @return array<int, string> */
    public function options(): array
    {
        return $this->db->fetchPairs(
            "SELECT id, title FROM health_packages
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY sort_order ASC"
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

    /**
     * Encode the bilingual bullet lists for the items_json column.
     *
     * @param list<string> $englishItems
     * @param list<string> $amharicItems
     */
    public static function encodeItems(array $englishItems, array $amharicItems): string
    {
        $clean = static fn (array $items): array => array_values(array_filter(
            array_map(static fn (string $i): string => trim($i), $items),
            static fn (string $i): bool => $i !== '',
        ));

        return json_encode(
            ['en' => $clean($englishItems), 'am' => $clean($amharicItems)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Revenue by package, for the finance dashboard.
     *
     * Counts only verified money against completed or confirmed bookings, so
     * an unverified slip never inflates the figure.
     *
     * @return list<array{title:string, bookings:int, revenue:string}>
     */
    public function revenueBreakdown(string $from, string $to): array
    {
        return $this->db->fetchAll(
            "SELECT p.title,
                    COUNT(a.id)                 AS bookings,
                    COALESCE(SUM(a.amount_paid), 0) AS revenue
             FROM health_packages p
             LEFT JOIN appointments a
                    ON a.package_id = p.id
                   AND a.deleted_at IS NULL
                   AND a.status IN ('confirmed', 'completed')
                   AND a.appointment_date BETWEEN :from AND :to
             WHERE p.deleted_at IS NULL
             GROUP BY p.id, p.title
             ORDER BY revenue DESC",
            ['from' => $from, 'to' => $to],
        );
    }
}
