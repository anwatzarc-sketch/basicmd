<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\Patient;
use DateTimeImmutable;

/**
 * MPI persistence port for PatientDeduplicationService (FRS 8.1).
 *
 * Method names mirror the two matching levels the FRS specifies exactly,
 * so the service's own workflow reads as a direct translation of the
 * spec: exact identifier match, then exact demographic match, then
 * register.
 */
interface PatientRepositoryInterface
{
    public function findById(int $id): ?Patient;

    /**
     * Level 1a: exact national_id match.
     *
     * A single nullable result is safe here specifically because
     * national_id carries a UNIQUE database constraint - two rows can
     * never both match the same value, so there is no ambiguity to detect.
     */
    public function findByNationalId(string $nationalId): ?Patient;

    /**
     * Level 1b: exact phone_number match (E.164).
     *
     * Returns every match, not just one - phone_number is NOT unique (a
     * household legitimately shares one), so the caller must be able to
     * tell "no match", "exactly one match" and "more than one match"
     * apart. See PatientDeduplicationService and PatientException::ambiguousMatch().
     *
     * @return list<Patient>
     */
    public function findAllByPhone(string $phoneE164): array;

    /**
     * Level 2: exact first_name + last_name + date_of_birth match.
     *
     * Same reasoning as findAllByPhone() - this combination has no unique
     * constraint, so every match is returned.
     *
     * @return list<Patient>
     */
    public function findAllByDemographics(string $firstName, string $lastName, DateTimeImmutable $dateOfBirth): array;

    /**
     * Insert a new patient row and return its id.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int;

    /**
     * Turn a plaintext allergy list into the representation stored in
     * patients.allergies_encrypted. How that representation is protected
     * (AES-256-GCM, currently) is an Infrastructure decision the Domain
     * layer deliberately does not need to know - this method exists on
     * the port precisely so PatientDeduplicationService can persist an
     * allergy list without depending on the concrete Encryptor class.
     *
     * @param list<string> $allergies
     */
    public function encryptAllergies(array $allergies): string;
}
