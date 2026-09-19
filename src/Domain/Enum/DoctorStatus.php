<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * Whether a doctor is listed publicly and bookable.
 *
 * ON_LEAVE keeps the profile visible on the website (so the roster does not
 * appear to shrink) while removing every slot from the availability matrix.
 */
enum DoctorStatus: string
{
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
    case ON_LEAVE = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'Active',
            self::INACTIVE => 'Inactive',
            self::ON_LEAVE => 'On Leave',
        };
    }

    public function translationKey(): string
    {
        return 'doctor_status.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE   => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::INACTIVE => 'bg-slate-100 text-slate-600 border-slate-200',
            self::ON_LEAVE => 'bg-amber-100 text-amber-800 border-amber-200',
        };
    }

    /** Shown in the public doctors grid. */
    public function isPubliclyVisible(): bool
    {
        return $this !== self::INACTIVE;
    }

    /** Offered as a selectable doctor in the booking engine. */
    public function isBookable(): bool
    {
        return $this === self::ACTIVE;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::ACTIVE, self::ON_LEAVE, self::INACTIVE];
    }
}
