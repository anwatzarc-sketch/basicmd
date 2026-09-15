<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * The four payment methods FRS 7.2 defines for receivable_payments.
 *
 * Distinct from the existing PaymentChannel enum (bank_transfer,
 * mobile_money, cash_on_arrival), which belongs to the original
 * patient-uploads-a-slip payment flow on `payments`/`payment_methods` -
 * a different table, a different workflow, a different set of values.
 * This one is what an accountant selects when posting DIRECTLY against
 * an encounter's receivable balance.
 */
enum ReceivablePaymentMethod: string
{
    case CASH            = 'Cash';
    case MOBILE_MONEY    = 'Mobile Money';
    case BANK_TRANSFER   = 'Bank Transfer';
    case INSURANCE       = 'Insurance';

    /** @return list<self> */
    public static function all(): array
    {
        return [self::CASH, self::MOBILE_MONEY, self::BANK_TRANSFER, self::INSURANCE];
    }
}
