<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * What portion of the bill a given proof-of-payment covers.
 */
enum PaymentKind: string
{
    case DEPOSIT = 'deposit';
    case BALANCE = 'balance';
    case FULL    = 'full';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Deposit',
            self::BALANCE => 'Remaining Balance',
            self::FULL    => 'Full Payment',
        };
    }

    public function translationKey(): string
    {
        return 'payment_kind.' . $this->value;
    }

    /**
     * The appointment-level status this proof implies once verified.
     * A deposit leaves a balance outstanding; the other two settle it.
     */
    public function resultingPaymentStatus(): PaymentStatus
    {
        return match ($this) {
            self::DEPOSIT           => PaymentStatus::DEPOSIT_PAID,
            self::BALANCE, self::FULL => PaymentStatus::PAID,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::DEPOSIT, self::BALANCE, self::FULL];
    }
}
