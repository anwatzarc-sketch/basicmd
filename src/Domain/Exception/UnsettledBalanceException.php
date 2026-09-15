<?php

declare(strict_types=1);

namespace Aster\Domain\Exception;

use Aster\Domain\ValueObject\Balance;
use RuntimeException;

/**
 * FRS 8.3: thrown by BillingService::processDischargeOrClosure() when the
 * receivable balance remains unsettled (> 0) and no valid clearance
 * override applies. Named exactly as the FRS specifies, since it is
 * referenced by that literal name in the acceptance criteria (AC-DIS-02).
 */
final class UnsettledBalanceException extends RuntimeException
{
    public function __construct(public readonly Balance $balance, public readonly int $encounterId)
    {
        parent::__construct(
            sprintf(
                'Encounter #%d has an outstanding balance of %s and cannot be discharged or closed until it is settled or an authorised override is applied.',
                $encounterId,
                $balance->format(),
            ),
            409,
        );
    }
}
