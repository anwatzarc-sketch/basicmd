<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Settlement state of an appointment as a whole.
 *
 * Distinct from ProofStatus, which tracks one individual slip. An appointment
 * can hold several proofs (a deposit, then the balance; or a rejected slip
 * followed by a corrected one) while carrying a single payment status here.
 */
enum PaymentStatus: string
{
    case UNPAID                = 'unpaid';
    case AWAITING_VERIFICATION = 'awaiting_verification';
    case DEPOSIT_PAID          = 'deposit_paid';
    case PAID                  = 'paid';
    case REFUNDED              = 'refunded';
    case WAIVED                = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID                => 'Unpaid',
            self::AWAITING_VERIFICATION => 'Awaiting Verification',
            self::DEPOSIT_PAID          => 'Deposit Paid',
            self::PAID                  => 'Paid in Full',
            self::REFUNDED              => 'Refunded',
            self::WAIVED                => 'Waived',
        };
    }

    public function translationKey(): string
    {
        return 'payment_status.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::UNPAID                => 'bg-slate-100 text-slate-700 border-slate-200',
            self::AWAITING_VERIFICATION => 'bg-amber-100 text-amber-800 border-amber-200',
            self::DEPOSIT_PAID          => 'bg-sky-100 text-sky-800 border-sky-200',
            self::PAID                  => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::REFUNDED              => 'bg-rose-100 text-rose-800 border-rose-200',
            self::WAIVED                => 'bg-violet-100 text-violet-800 border-violet-200',
        };
    }

    /** Finance still has something to review or chase. */
    public function needsAttention(): bool
    {
        return match ($this) {
            self::AWAITING_VERIFICATION, self::UNPAID, self::DEPOSIT_PAID => true,
            self::PAID, self::REFUNDED, self::WAIVED => false,
        };
    }

    /** Money has actually landed (counts toward collected revenue). */
    public function isSettled(): bool
    {
        return match ($this) {
            self::PAID, self::DEPOSIT_PAID, self::WAIVED => true,
            self::UNPAID, self::AWAITING_VERIFICATION, self::REFUNDED => false,
        };
    }

    /**
     * Whether the patient may still submit a proof of payment.
     * A refunded or waived booking must not accept new slips.
     */
    public function acceptsProof(): bool
    {
        return match ($this) {
            self::UNPAID, self::AWAITING_VERIFICATION, self::DEPOSIT_PAID => true,
            self::PAID, self::REFUNDED, self::WAIVED => false,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::UNPAID,
            self::AWAITING_VERIFICATION,
            self::DEPOSIT_PAID,
            self::PAID,
            self::REFUNDED,
            self::WAIVED,
        ];
    }
}
