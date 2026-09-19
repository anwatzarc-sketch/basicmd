<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Exception;

use MediCareMini\Domain\Enum\EncounterStatus;
use RuntimeException;

/**
 * Encounter and bed-allocation errors (FRS 8.2). Mirrors BookingException's
 * shape: one class, one factory method per reason, a message written to
 * be shown directly to the staff member who triggered it.
 */
final class EncounterException extends RuntimeException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message, 409);
    }

    public static function patientNotFound(): self
    {
        return new self('That patient could not be found.', 'patient_not_found');
    }

    public static function encounterNotFound(): self
    {
        return new self('That encounter could not be found.', 'encounter_not_found');
    }

    public static function locationNotFound(): self
    {
        return new self('That location could not be found.', 'location_not_found');
    }

    /** FRS 8.2: "The method must not silently allocate an occupied bed." */
    public static function locationOccupied(): self
    {
        return new self(
            'That bed has just been allocated to another patient. Please choose a different bed.',
            'location_occupied',
        );
    }

    public static function notAnAdmissionLocation(): self
    {
        return new self(
            'That location is a transient (consultation/waiting) space and cannot receive an admission.',
            'not_admission_location',
        );
    }

    public static function notEligibleForAdmission(EncounterStatus $current): self
    {
        return new self(
            sprintf(
                'This encounter is %s and cannot be admitted - only Checked In or In Consultation encounters can be.',
                $current->label(),
            ),
            'invalid_state',
        );
    }

    public static function invalidPhysician(): self
    {
        return new self(
            'The selected physician is not an active, authorised physician.',
            'invalid_physician',
        );
    }

    public static function appointmentAlreadyCheckedIn(): self
    {
        return new self(
            'This appointment has already been checked in to an encounter.',
            'already_checked_in',
        );
    }

    /** BillingService's basic actor sanity check - see StaffDirectoryInterface::isActiveStaffUser(). */
    public static function invalidActor(): self
    {
        return new self(
            'The acting user is not a valid, active staff account.',
            'invalid_actor',
        );
    }

    /** QueueService::markWalkOut() - FRS 8.4: "Require a reason." */
    public static function walkOutReasonRequired(): self
    {
        return new self('A reason is required to mark a walk-out.', 'reason_required');
    }
}
