<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Infrastructure\Support\Slug;

/**
 * Shared INSERT/UPDATE/soft-delete/slug plumbing for the content repositories.
 *
 * Column names come from array keys supplied by the repository itself - never
 * from request data - and are wrapped in backticks with embedded backticks
 * stripped. Values always travel as bound parameters.
 *
 * @property-read Database $db
 */
trait CrudOperations
{
    /** The table this repository owns. Defined as a const on each class. */
    abstract protected function table(): string;

    /** @param array<string, mixed> $data */
    protected function insertRow(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO ' . Database::quoteIdentifier($this->table())
            . ' (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    protected function updateRow(int $id, array $data, bool $respectSoftDelete = true): bool
    {
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        $sql = 'UPDATE ' . Database::quoteIdentifier($this->table())
            . " SET {$assignments} WHERE id = :id"
            . ($respectSoftDelete ? ' AND deleted_at IS NULL' : '');

        return $this->db->execute($sql, $data) > 0;
    }

    /**
     * Soft delete.
     *
     * Content rows are referenced by appointments and articles, so they are
     * retired rather than removed; a hard DELETE would either fail on the
     * foreign key or null out a patient's booking history.
     */
    public function softDelete(int $id): bool
    {
        return $this->db->execute(
            'UPDATE ' . Database::quoteIdentifier($this->table())
            . ' SET deleted_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }

    public function restore(int $id): bool
    {
        return $this->db->execute(
            'UPDATE ' . Database::quoteIdentifier($this->table())
            . ' SET deleted_at = NULL WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }

    /** Raw row, used to build a before/after audit diff. @return array<string,mixed>|null */
    public function rawRow(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . Database::quoteIdentifier($this->table()) . ' WHERE id = :id',
            ['id' => $id],
        );
    }

    /** Flip an active/inactive status column. */
    public function toggleStatus(int $id): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT status FROM ' . Database::quoteIdentifier($this->table()) . ' WHERE id = :id',
            ['id' => $id],
        );

        if ($row === null) {
            return null;
        }

        $next = (string) $row['status'] === 'active' ? 'inactive' : 'active';

        $this->db->execute(
            'UPDATE ' . Database::quoteIdentifier($this->table()) . ' SET status = :s WHERE id = :id',
            ['s' => $next, 'id' => $id],
        );

        return $next;
    }

    /**
     * Build a URL-safe slug, unique within the table.
     *
     * Amharic titles transliterate to nothing useful, so a title with no
     * Latin characters falls back to a short random token rather than
     * producing an empty slug that would collide with every other one.
     */
    public function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Slug::make($source);
        $slug = $base;
        $n    = 1;

        while (true) {
            $sql    = 'SELECT COUNT(*) FROM ' . Database::quoteIdentifier($this->table()) . ' WHERE slug = :slug';
            $params = ['slug' => $slug];

            if ($ignoreId !== null) {
                $sql         .= ' AND id <> :id';
                $params['id'] = $ignoreId;
            }

            if ($this->db->fetchInt($sql, $params) === 0) {
                return $slug;
            }

            $slug = $base . '-' . (++$n);
        }
    }

}
