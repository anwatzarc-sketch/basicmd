<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/** The seven consumption-ledger categories FRS 7.1 defines. */
enum LedgerCategory: string
{
    case REGISTRATION  = 'Registration';
    case CONSULTATION  = 'Consultation';
    case IMAGING       = 'Imaging';
    case LAB           = 'Lab';
    case PHARMACY      = 'Pharmacy';
    case ROOM_FEE      = 'Room Fee';
    case SURGICAL      = 'Surgical';

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::REGISTRATION,
            self::CONSULTATION,
            self::IMAGING,
            self::LAB,
            self::PHARMACY,
            self::ROOM_FEE,
            self::SURGICAL,
        ];
    }
}
