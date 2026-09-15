<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Bookable two-hour consultation blocks.
 *
 * Values match the `appointments.time_slot` ENUM exactly. The gap between
 * 12:00 and 14:00 is the clinic's lunch closure, which is why the four blocks
 * are not contiguous.
 */
enum TimeSlot: string
{
    case MORNING_EARLY = '08:00-10:00';
    case MORNING_LATE  = '10:00-12:00';
    case AFTERNOON     = '14:00-16:00';
    case EVENING       = '16:00-18:00';

    /** Display form with an en dash, matching the original prototype. */
    public function label(): string
    {
        return str_replace('-', "\u{2013}", $this->value);
    }

    public function startTime(): string
    {
        return explode('-', $this->value)[0];
    }

    public function endTime(): string
    {
        return explode('-', $this->value)[1];
    }

    /** Absolute start instant of this slot on a given date, in clinic time. */
    public function startsAt(DateTimeImmutable $date, DateTimeZone $tz): DateTimeImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $this->startTime()));

        return $date->setTimezone($tz)->setTime($h, $m, 0);
    }

    public function endsAt(DateTimeImmutable $date, DateTimeZone $tz): DateTimeImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $this->endTime()));

        return $date->setTimezone($tz)->setTime($h, $m, 0);
    }

    public function translationKey(): string
    {
        return 'time_slot.' . str_replace([':', '-'], ['', '_'], $this->value);
    }

    public function periodLabel(): string
    {
        return match ($this) {
            self::MORNING_EARLY, self::MORNING_LATE => 'Morning',
            self::AFTERNOON, self::EVENING          => 'Afternoon',
        };
    }

    /**
     * Ordered for rendering the availability matrix left to right.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::MORNING_EARLY, self::MORNING_LATE, self::AFTERNOON, self::EVENING];
    }
}
