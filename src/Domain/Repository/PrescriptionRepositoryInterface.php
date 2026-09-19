<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use MediCareMini\Domain\Entity\EPrescription;

interface PrescriptionRepositoryInterface
{
    public function findById(int $id): ?EPrescription;

    /** @return list<EPrescription> */
    public function forEncounter(int $encounterId): array;

    /**
     * Every prescription across a patient's whole encounter history, most
     * recent first - the Patient Detail page's Prescriptions tab. Same
     * join-through-encounters reasoning as the other forPatient() ports.
     *
     * @return list<EPrescription>
     */
    public function forPatient(int $patientId, int $limit = 100): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** Flip is_dispensed - the only field FRS 6.3 allows to change after creation. */
    public function markDispensed(int $id): bool;
}
