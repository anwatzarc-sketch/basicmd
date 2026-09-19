<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/** The eight groups FRS 5.2 specifies. */
enum BloodGroup: string
{
    case A_POSITIVE  = 'A+';
    case A_NEGATIVE  = 'A-';
    case B_POSITIVE  = 'B+';
    case B_NEGATIVE  = 'B-';
    case AB_POSITIVE = 'AB+';
    case AB_NEGATIVE = 'AB-';
    case O_POSITIVE  = 'O+';
    case O_NEGATIVE  = 'O-';

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::A_POSITIVE, self::A_NEGATIVE,
            self::B_POSITIVE, self::B_NEGATIVE,
            self::AB_POSITIVE, self::AB_NEGATIVE,
            self::O_POSITIVE, self::O_NEGATIVE,
        ];
    }
}
