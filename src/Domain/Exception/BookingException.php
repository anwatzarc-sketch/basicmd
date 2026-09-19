<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Exception;

use MediCareMini\Domain\Enum\AppointmentStatus;
use MediCareMini\Domain\Enum\ProofStatus;
use RuntimeException;

/**
 * A booking was refused for a business reason rather than a bad input.
 *
 * Every message here is written to be shown verbatim to a patient: it says
 * what happened and what to do next, without exposing internal capacity
 * figures or another patient's existence.
 */
final class BookingException extends RuntimeException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message, 409);
    }

    public static function slotFull(): self
    {
        return new self(
            'That time slot has just been taken. Please choose another time or a different doctor.',
            'slot_full',
        );
    }

    public static function doctorAtDailyCapacity(): self
    {
        return new self(
            'This doctor is fully booked on the date you selected. Please try another date.',
            'daily_capacity',
        );
    }

    public static function doctorUnavailable(): self
    {
        return new self(
            'This doctor is not available on the date you selected. Please choose another date or doctor.',
            'doctor_unavailable',
        );
    }

    /** The unique slot guard fired: the same phone already holds this slot. */
    public static function duplicateBooking(): self
    {
        return new self(
            'You already have a booking for this doctor at this time. Check your email for the confirmation.',
            'duplicate',
        );
    }

    public static function tooSoon(int $leadHours): self
    {
        return new self(
            "Bookings must be made at least {$leadHours} hours in advance. For urgent care, please call our emergency line.",
            'lead_time',
        );
    }

    public static function tooFarAhead(int $horizonDays): self
    {
        return new self(
            "Bookings can only be made up to {$horizonDays} days ahead. Please choose an earlier date.",
            'horizon',
        );
    }

    public static function clinicClosed(): self
    {
        return new self(
            'The clinic is closed on the date you selected. Please choose another day.',
            'closed',
        );
    }

    public static function dateInPast(): self
    {
        return new self('That date has already passed. Please choose a future date.', 'past');
    }

    public static function invalidTransition(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self(
            sprintf('An appointment cannot go from %s to %s.', $from->label(), $to->label()),
            'invalid_transition',
        );
    }

    public static function notFound(): self
    {
        return new self('We could not find a booking with that reference.', 'not_found');
    }

    /**
     * Too late for the patient to cancel online.
     *
     * The cut-off exists because the front desk is already preparing the room
     * and may have turned away a walk-in for the slot. Inside the window the
     * patient must call, so a person can decide what to do with the space.
     */
    public static function notCancellable(): self
    {
        return new self(
            'This appointment is too close to its start time to cancel online. Please call us and we will help.',
            'cancel_window',
        );
    }

    /**
     * A second reviewer acted on a proof that was already resolved.
     *
     * Surfaced rather than swallowed: the officer looking at a stale page
     * needs to know their click did nothing, or they will assume the money
     * was credited when someone else may have rejected it.
     */
    public static function proofAlreadyResolved(ProofStatus $status): self
    {
        return new self(
            sprintf(
                'This payment was already marked as %s by another reviewer. Refresh the page to see the current status.',
                $status->label(),
            ),
            'proof_resolved',
        );
    }

    /** The patient tried to act on a booking that is already closed out. */
    public static function alreadyFinalised(AppointmentStatus $status): self
    {
        return new self(
            sprintf('This booking is already marked as %s and can no longer be changed.', $status->label()),
            'finalised',
        );
    }
}
