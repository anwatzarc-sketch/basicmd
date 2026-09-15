<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\EPrescription;

interface PrescriptionRepositoryInterface
{
    public function findById(int $id): ?EPrescription;

    /** @return list<EPrescription> */
    public function forEncounter(int $encounterId): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** Flip is_dispensed - the only field FRS 6.3 allows to change after creation. */
    public function markDispensed(int $id): bool;
}
