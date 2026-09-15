<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * consumption_ledger.mode (FRS 7.1) - deliberately its own 2-case enum
 * rather than reusing VisitType (OPD/IPD/ER): the ledger column itself is
 * ENUM('OPD','IPD') with no ER value, so reusing VisitType would let PHP
 * construct a LedgerMode::ER that the database would only catch at
 * INSERT time. An ER encounter's charges are logged under OPD or IPD
 * depending on whether the patient was subsequently admitted - the same
 * distinction BillingService cares about for the discharge gate.
 */
enum LedgerMode: string
{
    case OPD = 'OPD';
    case IPD = 'IPD';

    /** @return list<self> */
    public static function all(): array
    {
        return [self::OPD, self::IPD];
    }
}
