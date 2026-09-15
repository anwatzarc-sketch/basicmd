<?php

declare(strict_types=1);

namespace Aster\Domain\Services;

use Aster\Domain\Entity\Encounter;
use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\EncounterException;
use Aster\Domain\Repository\AuditLoggerInterface;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\Repository\PatientRepositoryInterface;
use Aster\Domain\Repository\StaffDirectoryInterface;
use Aster\Domain\Repository\TransactionManagerInterface;
use Aster\Domain\Repository\WardLocationRepositoryInterface;
use Aster\Domain\ValueObject\VisitNumber;
use DateTimeImmutable;
use RuntimeException;

/**
 * Encounter creation and the OPD-to-IPD conversion (FRS 8.2).
 *
 * upgradeOpdToIpd() is the concurrency-critical method: two staff members
 * racing to admit different patients into the SAME bed must never both
 * succeed. It copies the proven lock pattern from
 * AppointmentRepository::createWithCapacityCheck() - lock the scarce
 * resource row FIRST, re-check its state AFTER the lock is held (never
 * trust a read taken before locking), only then mutate both rows and
 * commit together.
 */
final readonly class EncounterService
{
    public function __construct(
        private EncounterRepositoryInterface $encounters,
        private PatientRepositoryInterface $patients,
        private WardLocationRepositoryInterface $wardLocations,
        private StaffDirectoryInterface $staff,
        private NumberSequenceInterface $sequence,
        private TransactionManagerInterface $transactions,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * Start a new encounter for an existing patient.
     *
     * "Any available doctor"-style deferred assignment does not apply here
     * - a physician is optional at check-in (assigned later by triage) -
     * but bed assignment, when given, is resolved and validated inside the
     * same transaction as the insert, for the same reason
     * BookingService::book() resolves a doctor inside its transaction: an
     * unvalidated foreign key is not a business decision, it is a bug
     * waiting for a concurrent request to expose.
     */
    public function startEncounter(int $patientId, VisitType $visitType, ?int $locationId): Encounter
    {
        $patient = $this->patients->findById($patientId);

        if ($patient === null) {
            throw EncounterException::patientNotFound();
        }

        return $this->transactions->transactional(function () use ($patientId, $visitType, $locationId): Encounter {
            $resolvedLocationId = null;

            if ($locationId !== null) {
                $resolvedLocationId = $this->allocateLocation($locationId, $visitType);
            }

            $visitNumber = $this->generateVisitNumber();

            $encounterId = $this->encounters->create([
                'patient_visit_number' => $visitNumber->value,
                'patient_id'           => $patientId,
                'visit_type'           => $visitType->value,
                'status'               => EncounterStatus::CHECKED_IN->value,
                'current_location_id'  => $resolvedLocationId,
            ]);

            $encounter = $this->encounters->findById($encounterId);

            if ($encounter === null) {
                throw new RuntimeException('Encounter vanished immediately after creation.');
            }

            $this->audit->record(
                AuditLoggerInterface::ENCOUNTER_STARTED,
                'encounter',
                $encounterId,
                sprintf('Started %s encounter %s', $visitType->label(), $visitNumber->value),
            );

            return $encounter;
        });
    }

    /**
     * Validate and occupy a location at check-in time.
     *
     * Runs INSIDE startEncounter()'s transaction. Locks the row before
     * checking occupancy for the same reason upgradeOpdToIpd() does - a
     * read taken before the lock could already be stale by the time this
     * function decides anything.
     */
    private function allocateLocation(int $locationId, VisitType $visitType): int
    {
        $location = $this->wardLocations->findForUpdate($locationId);

        if ($location === null) {
            throw EncounterException::locationNotFound();
        }

        // A transient (consultation/waiting) location has no occupancy
        // constraint - only IPD-style bed assignment does.
        if ($location->isTransient) {
            return $location->id;
        }

        if ($location->isOccupied) {
            throw EncounterException::locationOccupied();
        }

        $this->wardLocations->markOccupied($location->id);

        return $location->id;
    }

    /**
     * Convert an OPD/ER encounter to IPD, allocating a bed.
     *
     * Preconditions (FRS 8.2), all checked AFTER acquiring the encounter
     * lock and the bed lock respectively - never before:
     *   - the encounter exists and is CHECKED_IN or IN_CONSULTATION
     *   - the target location exists and is an admission location (not
     *     transient)
     *   - the target bed is not occupied
     *   - the physician is an active physician
     */
    public function upgradeOpdToIpd(VisitNumber $patientVisitNumber, int $targetBedId, int $physicianId): void
    {
        if (!$this->staff->isActivePhysician($physicianId)) {
            throw EncounterException::invalidPhysician();
        }

        $this->transactions->transactional(function () use ($patientVisitNumber, $targetBedId, $physicianId): void {
            // --- Lock and validate the encounter -------------------------
            $encounter = $this->encounters->findByVisitNumberForUpdate($patientVisitNumber);

            if ($encounter === null) {
                throw EncounterException::encounterNotFound();
            }

            if (!$encounter->status->isAdmissionEligible()) {
                throw EncounterException::notEligibleForAdmission($encounter->status);
            }

            // --- Lock and validate the target bed, AFTER the lock -------
            $bed = $this->wardLocations->findForUpdate($targetBedId);

            if ($bed === null) {
                throw EncounterException::locationNotFound();
            }

            if ($bed->isTransient) {
                throw EncounterException::notAnAdmissionLocation();
            }

            if ($bed->isOccupied) {
                throw EncounterException::locationOccupied();
            }

            // --- Mutate both rows; commit together -----------------------
            $this->wardLocations->markOccupied($bed->id);

            $admittedAt = new DateTimeImmutable();

            $this->encounters->update($encounter->id, [
                'visit_type'            => VisitType::IPD->value,
                'status'                => EncounterStatus::ADMITTED->value,
                'current_location_id'   => $bed->id,
                'primary_physician_id'  => $physicianId,
                'admitted_at'           => $admittedAt->format('Y-m-d H:i:s'),
            ]);

            // ENC-006: the conversion must produce an audit event, as part
            // of this same transaction - not a follow-up call a caller
            // might forget, and not written outside the transaction where
            // it could survive a rollback of the actual state change.
            $this->audit->record(
                AuditLoggerInterface::ENCOUNTER_ADMITTED,
                'encounter',
                $encounter->id,
                sprintf(
                    'Admitted %s to %s (physician #%d)',
                    $encounter->patientVisitNumber->value,
                    $bed->displayLabel(),
                    $physicianId,
                ),
            );
        });
    }

    private function generateVisitNumber(): VisitNumber
    {
        $today = new DateTimeImmutable();
        $scope = 'visit:' . $today->format('Ymd');

        return VisitNumber::forDate($today, $this->sequence->next($scope));
    }
}
