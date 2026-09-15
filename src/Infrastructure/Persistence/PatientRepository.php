<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\Patient;
use Aster\Domain\Repository\PatientRepositoryInterface;
use Aster\Domain\ValueObject\PhoneNumber;
use Aster\Infrastructure\Security\Encryptor;
use DateTimeImmutable;

/**
 * MPI persistence. Implements the Domain port (PatientRepositoryInterface)
 * so PatientDeduplicationService never touches SQL, and additionally
 * exposes the read/write surface the admin MPI screen (Stage 4) and the
 * allergy encryption boundary need - those go beyond the port because
 * they are Infrastructure-to-Presentation concerns, not something a
 * Domain service calls.
 */
final class PatientRepository implements PatientRepositoryInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function findById(int $id): ?Patient
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM patients WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : Patient::fromRow($row);
    }

    public function findByPid(string $pid): ?Patient
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM patients WHERE pid = :pid AND deleted_at IS NULL',
            ['pid' => $pid],
        );

        return $row === null ? null : Patient::fromRow($row);
    }

    public function findByNationalId(string $nationalId): ?Patient
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM patients WHERE national_id = :nid AND deleted_at IS NULL',
            ['nid' => $nationalId],
        );

        return $row === null ? null : Patient::fromRow($row);
    }

    /** @return list<Patient> */
    public function findAllByPhone(string $phoneE164): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM patients WHERE phone_number = :phone AND deleted_at IS NULL ORDER BY id ASC',
            ['phone' => $phoneE164],
        );

        return array_map(Patient::fromRow(...), $rows);
    }

    /** @return list<Patient> */
    public function findAllByDemographics(string $firstName, string $lastName, DateTimeImmutable $dateOfBirth): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM patients
             WHERE first_name = :first AND last_name = :last AND date_of_birth = :dob
               AND deleted_at IS NULL
             ORDER BY id ASC',
            ['first' => $firstName, 'last' => $lastName, 'dob' => $dateOfBirth->format('Y-m-d')],
        );

        return array_map(Patient::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO patients (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
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

        return $this->db->execute(
            "UPDATE patients SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
            $data,
        ) > 0;
    }

    /**
     * Decrypt a patient's allergy list.
     *
     * The one place a Patient's ciphertext is actually read back to
     * plaintext - see Patient's own docblock for why the entity itself
     * never does this. Returns an empty array (not null) when there is
     * nothing recorded, so a caller need not special-case "no allergies"
     * versus "allergies not yet asked about" any differently than an
     * empty list.
     *
     * @return list<string>
     */
    public function decryptAllergies(Patient $patient): array
    {
        if ($patient->allergiesEncrypted === null) {
            return [];
        }

        $json = $this->encryptor->decrypt($patient->allergiesEncrypted);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    /** Encrypt an allergy list for storage - the write-side counterpart to decryptAllergies(). */
    public function encryptAllergies(array $allergies): string
    {
        return $this->encryptor->encrypt(json_encode(array_values($allergies), JSON_THROW_ON_ERROR));
    }

    /**
     * Search for the admin MPI lookup screen (Stage 4) - exact national_id
     * or phone match first (highest confidence), else a name substring
     * search. Distinct named placeholders throughout, since
     * ATTR_EMULATE_PREPARES is false and a placeholder may appear only
     * once per statement (bin/check-sql.php enforces this).
     *
     * @return list<Patient>
     */
    public function search(string $term, int $limit = 25): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        // phone_number is always stored E.164-normalised (the same
        // PhoneNumber value object PatientDeduplicationService matches
        // with), but a staff member searching types it the way a patient
        // said it out loud - "0911 998 877", not "+251911998877". An
        // exact match against the raw term would silently never find
        // anyone by phone; normalise first and fall back to the raw term
        // only when it does not parse as a phone number at all.
        $phoneTerm = PhoneNumber::tryFrom($term)?->e164 ?? $term;

        [$nameClause, $nameParams] = Database::searchClause(['first_name', 'last_name'], $term, 'name');

        $rows = $this->db->fetchAll(
            "SELECT * FROM patients
             WHERE deleted_at IS NULL
               AND (national_id = :exact OR phone_number = :phone OR pid = :pid OR {$nameClause})
             ORDER BY
               CASE WHEN national_id = :exact2 THEN 0
                    WHEN phone_number = :phone2 THEN 1
                    WHEN pid = :pid2 THEN 1
                    ELSE 2 END,
               last_name ASC, first_name ASC
             LIMIT :limit",
            [
                'exact'  => $term,
                'phone'  => $phoneTerm,
                'pid'    => mb_strtoupper($term),
                'exact2' => $term,
                'phone2' => $phoneTerm,
                'pid2'   => mb_strtoupper($term),
                'limit'  => $limit,
                ...$nameParams,
            ],
        );

        return array_map(Patient::fromRow(...), $rows);
    }
}
