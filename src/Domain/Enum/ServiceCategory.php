<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

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

    /**
     * Categories carry the brand tint rather than one hue each.
     *
     * A six-colour key only helps a reader who has learned the mapping, and
     * nothing on the site publishes one - the chip already spells the category
     * out in words. Emergency is the single exception: a warning colour there
     * is doing safety work, not decoration.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::EMERGENCY => 'bg-rose-50 text-rose-700 border-rose-100',
            default         => 'bg-medical-50 text-medical-700 border-medical-100',
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
