<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * The four order states FRS 6.2 defines.
 *
 * Mirrors AppointmentStatus/EncounterStatus's canTransitionTo() idiom -
 * an order's status is validated the same deliberate way, not written as
 * a bare string.
 */
enum DiagnosticStatus: string
{
    case ORDERED     = 'ORDERED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED   = 'COMPLETED';
    case CANCELLED   = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::ORDERED     => 'Ordered',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED   => 'Completed',
            self::CANCELLED   => 'Cancelled',
        };
    }

    /** @return list<self> */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::ORDERED     => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
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

    /** @return list<self> */
    public static function all(): array
    {
        return [self::ORDERED, self::IN_PROGRESS, self::COMPLETED, self::CANCELLED];
    }
}
