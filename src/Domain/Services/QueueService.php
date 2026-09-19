<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Services;

use MediCareMini\Domain\Enum\EncounterStatus;
use MediCareMini\Domain\Exception\EncounterException;
use MediCareMini\Domain\Repository\AuditLoggerInterface;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;

/**
 * Queue walk-out tracking (FRS 8.4).
 *
 * The one rule this class exists to guarantee: leaving the workflow never
 * erases what the encounter already owes. There is no code path here that
 * touches consumption_ledger or receivable_payments at all - a walk-out
 * is purely a status change on the encounter, which is what makes "must
 * not use walk-out as a mechanism to erase debt" true by omission rather
 * than by a check against reversing it.
 */
final readonly class QueueService
{
    public function __construct(
        private EncounterRepositoryInterface $encounters,
        private AuditLoggerInterface $audit,
    ) {
    }

    public function markWalkOut(int $encounterId, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw EncounterException::walkOutReasonRequired();
        }

        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null) {
            throw EncounterException::encounterNotFound();
        }

        // FRS 8.4's own distinction: CHECKED_IN (not yet seen) becomes
        // LEFT_WITHOUT_BEING_SEEN; IN_CONSULTATION (already being seen)
        // becomes WALK_OUT. Any other current state has already been
        // admitted, discharged, or marked as one of these two - none of
        // those are a walk-out to report.
        $target = match ($encounter->status) {
            EncounterStatus::CHECKED_IN      => EncounterStatus::LEFT_WITHOUT_BEING_SEEN,
            EncounterStatus::IN_CONSULTATION => EncounterStatus::WALK_OUT,
            default                          => null,
        };

        if ($target === null || !$encounter->status->canTransitionTo($target)) {
            throw EncounterException::notEligibleForAdmission($encounter->status);
        }

        $this->encounters->update($encounterId, ['status' => $target->value]);

        $this->audit->record(
            AuditLoggerInterface::ENCOUNTER_WALK_OUT,
            'encounter',
            $encounterId,
            sprintf('%s: %s - %s', $encounter->patientVisitNumber->value, $target->label(), $reason),
        );
    }
}
