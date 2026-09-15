<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\WardLocation;
use Aster\Domain\Repository\WardLocationRepositoryInterface;

final class WardLocationRepository implements WardLocationRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function find(int $id): ?WardLocation
    {
        $row = $this->db->fetchOne('SELECT * FROM ward_locations WHERE id = :id', ['id' => $id]);

        return $row === null ? null : WardLocation::fromRow($row);
    }

    /**
     * Locks the row for the duration of the enclosing transaction - the
     * serialisation point EncounterService::upgradeOpdToIpd() relies on.
     * Mirrors AppointmentRepository::assertCapacityAvailable()'s
     * `SELECT ... FOR UPDATE` pattern, the proven precedent for this
     * exact kind of "reserve a scarce resource" race in this codebase.
     */
    public function findForUpdate(int $id): ?WardLocation
    {
        $row = $this->db->fetchOne('SELECT * FROM ward_locations WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $row === null ? null : WardLocation::fromRow($row);
    }

    public function markOccupied(int $id): void
    {
        $this->db->execute('UPDATE ward_locations SET is_occupied = 1 WHERE id = :id', ['id' => $id]);
    }

    public function markAvailable(int $id): void
    {
        $this->db->execute('UPDATE ward_locations SET is_occupied = 0 WHERE id = :id', ['id' => $id]);
    }

    /** @return list<WardLocation> */
    public function availableForAdmission(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM ward_locations
             WHERE is_transient = 0 AND is_occupied = 0
             ORDER BY ward_name ASC, room_number ASC, bed_number ASC",
        );

        return array_map(WardLocation::fromRow(...), $rows);
    }

    /** @return list<WardLocation> */
    public function all(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ward_locations ORDER BY ward_name ASC, room_number ASC, bed_number ASC',
        );

        return array_map(WardLocation::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO ward_locations (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
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
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        return $this->db->execute("UPDATE ward_locations SET {$assignments} WHERE id = :id", $data) > 0;
    }
}
