<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/** The three kinds of encounter FRS 5.5 defines. */
enum VisitType: string
{
    case OPD = 'OPD';
    case IPD = 'IPD';
    case ER  = 'ER';

    public function label(): string
    {
        return match ($this) {
            self::OPD => 'Outpatient',
            self::IPD => 'Inpatient',
            self::ER  => 'Emergency',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::OPD, self::IPD, self::ER];
    }
}
