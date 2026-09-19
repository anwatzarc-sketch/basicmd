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

    /**
     * Whether $target is reachable from here by any chain of legal
     * transitions, one hop or several.
     *
     * The laboratory needs this because its screens are phrased as
     * intents, not states: a technician keys results into a freshly
     * ordered specimen and presses "Verify & release", meaning ORDERED ->
     * IN_PROGRESS -> COMPLETED in one submit. Every hop of that path is
     * checked here, so the shortcut is a shortcut through the machine
     * rather than around it - and CANCELLED stays terminal, because
     * nothing leads out of it.
     */
    public function canReach(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        $seen  = [$this->value => true];
        $queue = $this->allowedTransitions();

        while ($queue !== []) {
            $status = array_shift($queue);

            if ($status === $target) {
                return true;
            }

            if (isset($seen[$status->value])) {
                continue;
            }

            $seen[$status->value] = true;
            $queue = [...$queue, ...$status->allowedTransitions()];
        }

        return false;
    }

    /**
     * How this order reads on a laboratory work queue.
     *
     * The same four states, named for what a technician is waiting to do
     * with the specimen rather than for the record's internal state. No
     * fifth state is introduced for it: a queue label is a presentation
     * of a status, and a status the transition machine does not know
     * about is a status nothing can validate.
     */
    public function labLabel(): string
    {
        return match ($this) {
            self::ORDERED     => 'Awaiting specimen',
            self::IN_PROGRESS => 'Pending result entry',
            self::COMPLETED   => 'Released for print',
            self::CANCELLED   => 'Cancelled',
        };
    }

    /** Status-pill class, in the app's existing chip-- family. */
    public function chipClass(): string
    {
        return match ($this) {
            self::ORDERED     => 'chip--partial',
            self::IN_PROGRESS => 'chip',
            self::COMPLETED   => 'chip--built',
            self::CANCELLED   => 'chip--absent',
        };
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
