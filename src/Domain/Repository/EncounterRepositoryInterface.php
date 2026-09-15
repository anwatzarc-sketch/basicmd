<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\Encounter;
use Aster\Domain\ValueObject\VisitNumber;

interface EncounterRepositoryInterface
{
    public function findById(int $id): ?Encounter;

    /**
     * Same as findById(), but takes a row lock held until the enclosing
     * transaction ends - used by BillingService::processDischargeOrClosure()
     * so the balance read and the status write are atomic with respect to
     * a concurrent ledger or payment insert against the same encounter.
     */
    public function findByIdForUpdate(int $id): ?Encounter;

    public function findByVisitNumber(VisitNumber $visitNumber): ?Encounter;

    /**
     * Same as findByVisitNumber(), but takes a row lock held until the
     * enclosing transaction ends. This is the serialisation point for
     * EncounterService::upgradeOpdToIpd() - see WardLocationRepositoryInterface's
     * docblock for why the lock belongs at the interface boundary, not in
     * the caller.
     */
    public function findByVisitNumberForUpdate(VisitNumber $visitNumber): ?Encounter;

    public function findByAppointmentId(int $appointmentId): ?Encounter;

    /**
     * Every encounter not yet in a terminal state, most recent first - the
     * workbench's default working list (FRS 10.2).
     *
     * @return list<Encounter>
     */
    public function active(int $limit = 100): array;

    /**
     * A patient's full encounter history, most recent first - the admin
     * patient-detail screen and the portal dashboard's own view of "my
     * visits" (FRS 10.1/10.6).
     *
     * @return list<Encounter>
     */
    public function forPatient(int $patientId, int $limit = 50): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool;
}
