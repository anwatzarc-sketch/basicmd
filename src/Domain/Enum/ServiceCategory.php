<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Grouping used by the public services filter and the admin catalogue.
 */
enum ServiceCategory: string
{
    case CLINICAL    = 'clinical';
    case DIAGNOSTICS = 'diagnostics';
    case IMAGING     = 'imaging';
    case PHARMACY    = 'pharmacy';
    case WELLNESS    = 'wellness';
    case EMERGENCY   = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::CLINICAL    => 'Clinical Care',
            self::DIAGNOSTICS => 'Diagnostics',
            self::IMAGING     => 'Imaging',
            self::PHARMACY    => 'Pharmacy',
            self::WELLNESS    => 'Wellness',
            self::EMERGENCY   => 'Emergency',
        };
    }

    public function translationKey(): string
    {
        return 'service_category.' . $this->value;
    }

    public function chipClass(): string
    {
        return match ($this) {
            self::CLINICAL    => 'bg-medical-50 text-medical-700 border-medical-100',
            self::DIAGNOSTICS => 'bg-sky-50 text-sky-700 border-sky-100',
            self::IMAGING     => 'bg-violet-50 text-violet-700 border-violet-100',
            self::PHARMACY    => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            self::WELLNESS    => 'bg-amber-50 text-amber-700 border-amber-100',
            self::EMERGENCY   => 'bg-rose-50 text-rose-700 border-rose-100',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::CLINICAL,
            self::DIAGNOSTICS,
            self::IMAGING,
            self::PHARMACY,
            self::WELLNESS,
            self::EMERGENCY,
        ];
    }
}
