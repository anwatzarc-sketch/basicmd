<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

enum FacilityStatus: string
{
    case OPERATIONAL = 'operational';
    case MAINTENANCE = 'maintenance';
    case UPGRADING   = 'upgrading';
    case OFFLINE     = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Operational',
            self::MAINTENANCE => 'Under Maintenance',
            self::UPGRADING   => 'Upgrading',
            self::OFFLINE     => 'Offline',
        };
    }

    public function translationKey(): string
    {
        return 'facility_status.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::MAINTENANCE => 'bg-amber-100 text-amber-800 border-amber-200',
            self::UPGRADING   => 'bg-sky-100 text-sky-800 border-sky-200',
            self::OFFLINE     => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }

    /**
     * An offline facility is hidden from the public site entirely -
     * advertising a room that cannot take patients generates complaints.
     */
    public function isPubliclyVisible(): bool
    {
        return $this !== self::OFFLINE;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::OPERATIONAL, self::MAINTENANCE, self::UPGRADING, self::OFFLINE];
    }
}
