<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use MediCareMini\Domain\Entity\PatientAccount;

/** patient_accounts persistence (FRS 5.3). */
interface PatientAccountRepositoryInterface
{
    public function findById(int $id): ?PatientAccount;

    /**
     * The UNIQUE constraint on patient_id (see migration 003's comment)
     * means this is always at most one row - the same "one portal
     * identity per patient" invariant a bare create() cannot enforce on
     * its own.
     */
    public function findByPatientId(int $patientId): ?PatientAccount;

    /**
     * Raw login credentials, resolved by the patient's PID - mirrors
     * UserRepository::findCredentials(). A raw array rather than a
     * hydrated PatientAccount: PatientAuthService needs to run a dummy
     * password verify even when no row is found, so the "does this exist"
     * check and entity hydration stay separate, the same reasoning
     * UserRepository::findCredentials() documents for staff login.
     *
     * @return array<string, mixed>|null
     */
    public function findCredentialsByPid(string $pid): ?array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    public function updatePasswordHash(int $accountId, string $passwordHash): void;

    public function recordLogin(int $accountId): void;
}
