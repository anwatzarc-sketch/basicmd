<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * Where a booking originated. Drives conversion reporting on the dashboard:
 * WEB is the only channel the marketing spend can take credit for.
 */
enum BookingSource: string
{
    case WEB      = 'web';
    case ADMIN    = 'admin';
    case PHONE    = 'phone';
    case WALK_IN  = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::WEB     => 'Website',
            self::ADMIN   => 'Admin Entry',
            self::PHONE   => 'Phone Call',
            self::WALK_IN => 'Walk-in',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::WEB     => 'bg-medical-50 text-medical-700 border-medical-100',
            self::ADMIN   => 'bg-violet-50 text-violet-700 border-violet-100',
            self::PHONE   => 'bg-sky-50 text-sky-700 border-sky-100',
            self::WALK_IN => 'bg-amber-50 text-amber-700 border-amber-100',
        };
    }

    /** Self-service bookings the patient made without staff involvement. */
    public function isSelfService(): bool
    {
        return $this === self::WEB;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::WEB, self::ADMIN, self::PHONE, self::WALK_IN];
    }
}
