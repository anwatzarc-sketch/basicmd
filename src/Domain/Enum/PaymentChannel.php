<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * How the patient settles: all manual, all verified by staff.
 *
 * There is no gateway integration by design. The patient transfers out of
 * band, uploads evidence, and a Finance officer confirms it - which is how
 * most clinic payments in Addis Ababa actually clear today.
 */
enum PaymentChannel: string
{
    case BANK_TRANSFER   = 'bank_transfer';
    case MOBILE_MONEY    = 'mobile_money';
    case CASH_ON_ARRIVAL = 'cash_on_arrival';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER   => 'Bank Transfer',
            self::MOBILE_MONEY    => 'Mobile Money',
            self::CASH_ON_ARRIVAL => 'Pay at Reception',
        };
    }

    public function translationKey(): string
    {
        return 'payment_channel.' . $this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::BANK_TRANSFER   => 'bank',
            self::MOBILE_MONEY    => 'phone',
            self::CASH_ON_ARRIVAL => 'cash',
        };
    }

    /**
     * Whether the patient is expected to upload a slip or screenshot.
     * Cash settles at the desk, so there is nothing to upload beforehand.
     */
    public function expectsProof(): bool
    {
        return $this !== self::CASH_ON_ARRIVAL;
    }

    /** Whether an account number should be displayed in the instructions. */
    public function showsAccountNumber(): bool
    {
        return $this !== self::CASH_ON_ARRIVAL;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::BANK_TRANSFER, self::MOBILE_MONEY, self::CASH_ON_ARRIVAL];
    }
}
