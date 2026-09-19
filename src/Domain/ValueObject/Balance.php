<?php

declare(strict_types=1);

namespace MediCareMini\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A SIGNED amount of money, integer minor units - the one place this
 * application needs an amount that can legitimately be negative.
 *
 * Money (the existing value object every other price, fee and payment in
 * this codebase uses) throws on construction if the amount is negative,
 * by design - a booking fee or a payment is never a negative quantity.
 * A receivable BALANCE is different: FRS 8.3's own clearance rule is
 * literally "ReceivableBalance <= 0.00" - overpayment produces a
 * negative balance, and that must be representable, not an error. Rather
 * than relax Money's invariant for every other caller in the application,
 * this narrow signed type exists for the one calculation that needs it.
 *
 * Never used for an individual charge or an individual payment amount -
 * those stay Money (always non-negative) at the line-item level; a
 * REVERSAL of one is a signed database row (per_unit_cost or amount_paid
 * going negative on a contra entry, see migration 005's own comment) but
 * is still summed into a Balance only at the point balance arithmetic
 * happens, in BillingService.
 */
final readonly class Balance
{
    private const int MINOR_UNITS = 100;

    private function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
    }

    public static function fromMinor(int $minor, string $currency = 'ETB'): self
    {
        return new self($minor, strtoupper($currency));
    }

    /** Parse a DECIMAL(10,2)/(12,2) column value straight from PDO, which may be negative. */
    public static function fromDatabase(string|float|int|null $value, string $currency = 'ETB'): self
    {
        $minor = (int) round(((float) ($value ?? 0)) * self::MINOR_UNITS);

        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency = 'ETB'): self
    {
        return new self(0, strtoupper($currency));
    }

    /** A non-negative Money amount lifted into signed Balance space, e.g. to seed a running total. */
    public static function fromMoney(Money $money): self
    {
        return new self($money->amountMinor, $money->currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amountMinor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinor > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    /** FRS 8.3's exact clearance test: ReceivableBalance <= 0.00. */
    public function isSettledOrCredit(): bool
    {
        return $this->amountMinor <= 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor > $other->amountMinor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountMinor === $other->amountMinor;
    }

    /** Major-unit float, for JSON responses only - never for arithmetic. */
    public function toMajor(): float
    {
        return $this->amountMinor / self::MINOR_UNITS;
    }

    /** Exact decimal string, may carry a leading '-'. */
    public function toDatabase(): string
    {
        return number_format($this->amountMinor / self::MINOR_UNITS, 2, '.', '');
    }

    /** "ETB -1,240.00" for a debit, "ETB 300.00" for a credit balance. */
    public function format(): string
    {
        return $this->currency . ' ' . number_format($this->toMajor(), 2, '.', ',');
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }
}
