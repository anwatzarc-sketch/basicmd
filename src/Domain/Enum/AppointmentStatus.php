<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Appointment lifecycle state machine.
 *
 *      pending ──► confirmed ──► completed
 *         │            │    └──► no_show
 *         └────────────┴───────► cancelled
 *
 * completed / cancelled / no_show are terminal. The allowed transitions live
 * here rather than in the controller so that the admin UI, the public
 * cancellation link and any future API all enforce the identical rule set.
 */
enum AppointmentStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW   = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'Pending Review',
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW   => 'No Show',
        };
    }

    /** Translation key resolved against lang/{locale}.php for patient-facing text. */
    public function translationKey(): string
    {
        return 'appointment_status.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING   => 'bg-amber-100 text-amber-800 border-amber-200',
            self::CONFIRMED => 'bg-teal-100 text-teal-800 border-teal-200',
            self::COMPLETED => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::CANCELLED => 'bg-rose-100 text-rose-800 border-rose-200',
            self::NO_SHOW   => 'bg-slate-200 text-slate-700 border-slate-300',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::PENDING   => 'bg-amber-500',
            self::CONFIRMED => 'bg-teal-500',
            self::COMPLETED => 'bg-emerald-500',
            self::CANCELLED => 'bg-rose-500',
            self::NO_SHOW   => 'bg-slate-400',
        };
    }

    /**
     * States reachable from this one.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING   => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::COMPLETED, self::NO_SHOW, self::CANCELLED],
            // Terminal states. A mistaken completion is corrected by creating
            // a new appointment, never by silently rewinding this one - the
            // audit trail must show what actually happened.
            self::COMPLETED, self::CANCELLED, self::NO_SHOW => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Still occupies capacity in the availability matrix. */
    public function occupiesSlot(): bool
    {
        return match ($this) {
            self::PENDING, self::CONFIRMED, self::COMPLETED => true,
            self::CANCELLED, self::NO_SHOW => false,
        };
    }

    /** Counts toward realised revenue in dashboard reporting. */
    public function countsAsRevenue(): bool
    {
        return $this === self::COMPLETED;
    }

    /** Whether a reminder email is still worth sending. */
    public function isReminderEligible(): bool
    {
        return $this === self::CONFIRMED;
    }

    /** The verb shown on the admin action button. */
    public function actionLabel(): string
    {
        return match ($this) {
            self::PENDING   => 'Confirm',
            self::CONFIRMED => 'Mark Completed',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW   => 'No Show',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::PENDING, self::CONFIRMED, self::COMPLETED, self::CANCELLED, self::NO_SHOW];
    }

    /**
     * Statuses that still need front-desk attention, for dashboard counters.
     *
     * @return list<self>
     */
    public static function openStates(): array
    {
        return [self::PENDING, self::CONFIRMED];
    }
}
