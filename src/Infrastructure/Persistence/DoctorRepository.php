<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\Doctor;
use Aster\Infrastructure\Support\Slug;

final class DoctorRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Doctors shown in the public roster (active and on-leave).
     *
     * @return list<Doctor>
     */
    public function publicList(?string $specialty = null, ?string $search = null): array
    {
        $where  = ["status <> 'inactive'", 'deleted_at IS NULL'];
        $params = [];

        if ($specialty !== null && $specialty !== '') {
            $where[]             = 'specialty = :specialty';
            $params['specialty'] = $specialty;
        }

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(
                ['full_name', 'specialty', 'credentials', 'full_name_am'],
                $search,
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM doctors WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, full_name ASC',
            $params,
        );

        return array_map(Doctor::fromRow(...), $rows);
    }

    /**
     * Doctors a patient may actually book right now.
     *
     * @return list<Doctor>
     */
    public function bookableList(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM doctors
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY sort_order ASC, full_name ASC"
        );

        return array_map(Doctor::fromRow(...), $rows);
    }

    /** @return list<Doctor> */
    public function adminList(?string $search = null, ?string $status = null): array
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== null && $search !== '') {
            [$clause, $searchParams] = Database::searchClause(
                ['full_name', 'specialty', 'phone'],
                $search,
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        if ($status !== null && $status !== '') {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        $rows = $this->db->fetchAll(
            'SELECT * FROM doctors WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, full_name ASC',
            $params,
        );

        return array_map(Doctor::fromRow(...), $rows);
    }

    public function findById(int $id): ?Doctor
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM doctors WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : Doctor::fromRow($row);
    }

    public function findBySlug(string $slug): ?Doctor
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM doctors WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug],
        );

        return $row === null ? null : Doctor::fromRow($row);
    }

    public function findByUserId(int $userId): ?Doctor
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM doctors WHERE user_id = :uid AND deleted_at IS NULL',
            ['uid' => $userId],
        );

        return $row === null ? null : Doctor::fromRow($row);
    }

    /**
     * Distinct specialties for the public filter chips.
     *
     * @return list<string>
     */
    public function specialties(): array
    {
        return array_column(
            $this->db->fetchAll(
                "SELECT DISTINCT specialty FROM doctors
                 WHERE status <> 'inactive' AND deleted_at IS NULL
                 ORDER BY specialty"
            ),
            'specialty',
        );
    }

    /** id => name, for select menus. @return array<int, string> */
    public function options(bool $bookableOnly = true): array
    {
        $condition = $bookableOnly ? "status = 'active'" : "status <> 'inactive'";

        return $this->db->fetchPairs(
            "SELECT id, full_name FROM doctors
             WHERE {$condition} AND deleted_at IS NULL
             ORDER BY sort_order ASC, full_name ASC"
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO doctors (' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns))
            . ') VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
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
            "UPDATE doctors SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
            $data,
        ) > 0;
    }

    /**
     * Soft delete.
     *
     * Hard deletion is never offered: appointments reference doctors, and the
     * clinical record of who a patient saw must survive a staff departure.
     */
    public function softDelete(int $id): bool
    {
        return $this->db->execute(
            "UPDATE doctors SET deleted_at = UTC_TIMESTAMP(), status = 'inactive' WHERE id = :id",
            ['id' => $id],
        ) > 0;
    }

    /** @return array<string, mixed>|null raw row, for audit diffing */
    public function rawRow(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM doctors WHERE id = :id', ['id' => $id]);
    }

    /**
     * Slugify a name and make it unique, appending -2, -3 ... when needed.
     *
     * Takes the RAW name and slugifies here, matching CrudOperations so every
     * repository behaves the same way. Previously this expected a
     * pre-slugified string while the trait version did its own slugifying,
     * which is what pushed callers into invoking the slugifier themselves.
     */
    public function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base   = Slug::make($source);
        $slug   = $base;
        $suffix = 1;

        while (true) {
            $params = ['slug' => $slug];
            $sql    = 'SELECT COUNT(*) FROM doctors WHERE slug = :slug';

            if ($ignoreId !== null) {
                $sql            .= ' AND id <> :id';
                $params['id']    = $ignoreId;
            }

            if ($this->db->fetchInt($sql, $params) === 0) {
                return $slug;
            }

            $slug = $base . '-' . (++$suffix);
        }
    }

    public function countActive(): int
    {
        return $this->db->fetchInt(
            "SELECT COUNT(*) FROM doctors WHERE status = 'active' AND deleted_at IS NULL"
        );
    }

    /**
     * Today's load per doctor for the dashboard.
     *
     * @return list<array{id:int, full_name:string, booked:int, daily_capacity:int}>
     */
    public function dailyLoad(string $date): array
    {
        return $this->db->fetchAll(
            "SELECT d.id, d.full_name, d.daily_capacity,
                    COUNT(a.id) AS booked
             FROM doctors d
             LEFT JOIN appointments a
                    ON a.doctor_id = d.id
                   AND a.appointment_date = :date
                   AND a.deleted_at IS NULL
                   AND a.status IN ('pending', 'confirmed', 'completed')
             WHERE d.status = 'active' AND d.deleted_at IS NULL
             GROUP BY d.id, d.full_name, d.daily_capacity
             ORDER BY booked DESC, d.full_name ASC",
            ['date' => $date],
        );
    }

    // --- Time off -------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function timeOff(int $doctorId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM doctor_time_off WHERE doctor_id = :d ORDER BY starts_on DESC LIMIT 50',
            ['d' => $doctorId],
        );
    }

    public function addTimeOff(int $doctorId, string $from, string $to, ?string $reason, ?int $createdBy): int
    {
        $this->db->execute(
            'INSERT INTO doctor_time_off (doctor_id, starts_on, ends_on, reason, created_by)
             VALUES (:d, :from, :to, :reason, :by)',
            ['d' => $doctorId, 'from' => $from, 'to' => $to, 'reason' => $reason, 'by' => $createdBy],
        );

        return $this->db->lastInsertId();
    }

    public function removeTimeOff(int $id): bool
    {
        return $this->db->execute('DELETE FROM doctor_time_off WHERE id = :id', ['id' => $id]) > 0;
    }
}
