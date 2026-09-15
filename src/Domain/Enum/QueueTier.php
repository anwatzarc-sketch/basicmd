<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Standard vs. Express (VIP) queue placement.
 *
 * Express is the priority-queue revenue engine: the patient pays a surcharge
 * (default +20%, configurable via EXPRESS_SURCHARGE_RATE) to be seen ahead of
 * standard bookings in the same slot. The multiplier is NOT hardcoded here -
 * PricingService reads the configured rate and passes it in, so finance can
 * change the premium without a deploy.
 */
enum QueueTier: string
{
    case STANDARD = 'standard';
    case EXPRESS  = 'express';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard',
            self::EXPRESS  => 'Express Priority',
        };
    }

    public function translationKey(): string
    {
        return 'queue_tier.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::STANDARD => 'bg-slate-100 text-slate-700 border-slate-200',
            self::EXPRESS  => 'bg-gold/15 text-amber-800 border-amber-300',
        };
    }

    /** Lower sorts first in the day's working queue. */
    public function sortWeight(): int
    {
        return match ($this) {
            self::EXPRESS  => 0,
            self::STANDARD => 1,
        };
    }

    public function appliesSurcharge(): bool
    {
        return $this === self::EXPRESS;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::STANDARD, self::EXPRESS];
    }
}
