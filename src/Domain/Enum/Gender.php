<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

enum Gender: string
{
    case MALE   = 'male';
    case FEMALE = 'female';
    case OTHER  = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MALE   => 'Male',
            self::FEMALE => 'Female',
            self::OTHER  => 'Other',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::MALE, self::FEMALE, self::OTHER];
    }
}
