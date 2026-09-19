<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\Encounter;
use MediCareMini\Domain\Enum\EncounterStatus;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\ValueObject\VisitNumber;

final class EncounterRepository implements EncounterRepositoryInterface
{
    /** Denormalised display labels joined in, mirroring AppointmentRepository::SELECT_BASE. */
    private const string SELECT_BASE = "
        SELECT e.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               u.full_name AS physician_name,
               CASE WHEN w.id IS NULL THEN NULL
                    ELSE CONCAT(w.ward_name, ' - Room ', w.room_number, ' - Bed ', w.bed_number)
               END AS location_label
        FROM encounters e
        JOIN patients p ON p.id = e.patient_id
        LEFT JOIN users u ON u.id = e.primary_physician_id
        LEFT JOIN ward_locations w ON w.id = e.current_location_id
    ";

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?Encounter
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE e.id = :id', ['id' => $id]);

        return $row === null ? null : Encounter::fromRow($row);
    }

    /** See EncounterRepositoryInterface::findByIdForUpdate() for the locking contract. */
    public function findByIdForUpdate(int $id): ?Encounter
    {
        // Unjoined, same reasoning as findByVisitNumberForUpdate(): MariaDB
        // has no `FOR UPDATE OF <alias>` (confirmed a hard syntax error on
        // this server), so a plain FOR UPDATE here would also lock the
        // joined patients/users/ward_locations rows, which nothing about a
        // discharge needs to hold.
        $row = $this->db->fetchOne('SELECT * FROM encounters WHERE id = :id FOR UPDATE', ['id' => $id]);

        return $row === null ? null : Encounter::fromRow($row);
    }

    public function findByVisitNumber(VisitNumber $visitNumber): ?Encounter
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE e.patient_visit_number = :vn',
            ['vn' => $visitNumber->value],
        );

        return $row === null ? null : Encounter::fromRow($row);
    }

    /** See EncounterRepositoryInterface::findByVisitNumberForUpdate() for the locking contract. */
    public function findByVisitNumberForUpdate(VisitNumber $visitNumber): ?Encounter
    {
        // Deliberately NOT SELECT_BASE: a plain `FOR UPDATE` on MariaDB
        // locks every row read by the statement, joins included - there is
        // no `FOR UPDATE OF <alias>` here to scope it (that syntax is
        // MySQL 8+ only; verified it is a hard syntax error on this
        // server's MariaDB 10.4). Joining patients/users/ward_locations
        // into a locking read would take out THEIR rows too, blocking
        // unrelated transactions that have every right to read a patient
        // or a bed concurrently. The domain check this lock protects
        // (status, visit_type) only ever needs columns native to
        // `encounters` itself, so an unjoined read is not just safer, it
        // is all that is actually required - the denormalised display
        // fields are for presentation, which never happens inside this
        // locked window.
        $row = $this->db->fetchOne(
            'SELECT * FROM encounters WHERE patient_visit_number = :vn FOR UPDATE',
            ['vn' => $visitNumber->value],
        );

        return $row === null ? null : Encounter::fromRow($row);
    }

    public function findByAppointmentId(int $appointmentId): ?Encounter
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE e.appointment_id = :aid',
            ['aid' => $appointmentId],
        );

        return $row === null ? null : Encounter::fromRow($row);
    }

    /** @return list<Encounter> */
    public function forPatient(int $patientId, int $limit = 50): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE e.patient_id = :pid ORDER BY e.created_at DESC LIMIT :limit',
            ['pid' => $patientId, 'limit' => $limit],
        );

        return array_map(Encounter::fromRow(...), $rows);
    }

    /** @return list<Encounter> */
    public function active(int $limit = 100): array
    {
        // Built from the enum rather than a literal list, the same idiom
        // AppointmentRepository::occupyingStatusList() uses - safe to
        // interpolate because the values come from EncounterStatus cases,
        // never from user input.
        $terminal = implode(', ', array_map(
            static fn (EncounterStatus $s): string => "'" . $s->value . "'",
            array_filter(EncounterStatus::all(), static fn (EncounterStatus $s): bool => $s->isTerminal()),
        ));

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . " WHERE e.status NOT IN ({$terminal}) ORDER BY e.created_at DESC LIMIT :limit",
            ['limit' => $limit],
        );

        return array_map(Encounter::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO encounters (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
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

        return $this->db->execute("UPDATE encounters SET {$assignments} WHERE id = :id", $data) > 0;
    }
}
