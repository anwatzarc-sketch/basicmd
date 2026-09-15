<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * The lifecycle of a single encounter (FRS 5.5).
 *
 * Mirrors the state-machine idiom AppointmentStatus already uses in this
 * codebase (canTransitionTo() / isTerminal()) rather than introducing a
 * new one: an encounter's status must be validated the same deliberate
 * way an appointment's is - EncounterService and BillingService call
 * canTransitionTo() rather than writing a bare status string.
 */
enum EncounterStatus: string
{
    case CHECKED_IN                = 'CHECKED_IN';
    case IN_CONSULTATION           = 'IN_CONSULTATION';
    case ADMITTED                  = 'ADMITTED';
    case DISCHARGED                = 'DISCHARGED';
    case WALK_OUT                  = 'WALK_OUT';
    case LEFT_WITHOUT_BEING_SEEN   = 'LEFT_WITHOUT_BEING_SEEN';

    public function label(): string
    {
        return match ($this) {
            self::CHECKED_IN              => 'Checked In',
            self::IN_CONSULTATION         => 'In Consultation',
            self::ADMITTED                 => 'Admitted',
            self::DISCHARGED               => 'Discharged',
            self::WALK_OUT                  => 'Walk-out',
            self::LEFT_WITHOUT_BEING_SEEN    => 'Left Without Being Seen',
        };
    }

    /**
     * Chip colour per the FRS's status-badge table (section 3.3). Aliased
     * onto the existing .chip--* classes rather than a new palette - see
     * Stage 4's design-token decision.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::CHECKED_IN      => 'chip--built',
            self::ADMITTED        => 'chip--partial',
            self::WALK_OUT, self::LEFT_WITHOUT_BEING_SEEN => 'chip--absent',
            // Not specified by the FRS table - IN_CONSULTATION and
            // DISCHARGED get a reasonable in-between and a neutral resting
            // state respectively, reusing the same three-value vocabulary.
            self::IN_CONSULTATION => 'chip--partial',
            self::DISCHARGED       => 'chip--built',
        };
    }

    /** @return list<self> */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::CHECKED_IN => [
                self::IN_CONSULTATION, self::ADMITTED, self::DISCHARGED,
                self::WALK_OUT, self::LEFT_WITHOUT_BEING_SEEN,
            ],
            self::IN_CONSULTATION => [self::ADMITTED, self::DISCHARGED, self::WALK_OUT],
            self::ADMITTED        => [self::DISCHARGED],
            self::DISCHARGED, self::WALK_OUT, self::LEFT_WITHOUT_BEING_SEEN => [],
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

    /** FRS 8.2: upgradeOpdToIpd() requires CHECKED_IN or IN_CONSULTATION. */
    public function isAdmissionEligible(): bool
    {
        return $this === self::CHECKED_IN || $this === self::IN_CONSULTATION;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::CHECKED_IN,
            self::IN_CONSULTATION,
            self::ADMITTED,
            self::DISCHARGED,
            self::WALK_OUT,
            self::LEFT_WITHOUT_BEING_SEEN,
        ];
    }
}
