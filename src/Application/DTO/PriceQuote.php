<?php

declare(strict_types=1);

namespace MediCareMini\Application\DTO;

use MediCareMini\Domain\ValueObject\Money;

/**
 * An immutable price breakdown.
 *
 * Carries every component rather than a single total so the booking summary
 * can show the patient exactly how the number was reached - an unexplained
 * surcharge is the most common reason a checkout is abandoned.
 */
final readonly class PriceQuote
{
    public function __construct(
        public Money $base,
        public Money $surcharge,
        public Money $total,
        /** Amount payable up front to hold the slot. */
        public Money $depositDue,
        public bool $requiresDeposit,
        public float $surchargeRate,
    ) {
    }

    public function hasSurcharge(): bool
    {
        return $this->surcharge->isPositive();
    }

    public function isFree(): bool
    {
        return $this->total->isZero();
    }

    /** What remains after the deposit is settled. */
    public function balanceAfterDeposit(): Money
    {
        return $this->total->subtract($this->depositDue);
    }
}
