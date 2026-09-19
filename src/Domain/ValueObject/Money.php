<?php

declare(strict_types=1);

namespace MediCareMini\Domain\ValueObject;

use InvalidArgumentException;

/**
 * An amount of money, held as integer minor units (santim for ETB).
 *
 * Floats are never used for money anywhere in this application. A price of
 * ETB 2,500.00 is 250000 santim; arithmetic stays exact, and the only
 * rounding happens at explicitly-marked boundaries (percentage surcharges,
 * deposit fractions) where it is unavoidable.
 */
final readonly class Money
{
    private const int MINOR_UNITS = 100;

    private function __construct(
        public int $amountMinor,
        public string $currency,
    ) {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromMinor(int $minor, string $currency = 'ETB'): self
    {
        return new self($minor, strtoupper($currency));
    }

    /** Build from a major-unit amount, e.g. 2500.00 -> 250000 santim. */
    public static function fromMajor(int|float|string $major, string $currency = 'ETB'): self
    {
        // Round at the boundary where a float first enters the system, then
        // never touch a float again.
        $minor = (int) round(((float) $major) * self::MINOR_UNITS);

        return new self($minor, strtoupper($currency));
    }

    /** Parse a DECIMAL(10,2) column value straight from PDO. */
    public static function fromDatabase(string|float|int|null $value, string $currency = 'ETB'): self
    {
        return self::fromMajor($value ?? 0, $currency);
    }

    public static function zero(string $currency = 'ETB'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amountMinor > $this->amountMinor) {
            throw new InvalidArgumentException('Subtraction would produce a negative amount.');
        }

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    /**
     * Multiply by a rate, e.g. the 0.20 express surcharge.
     * Rounds half-up to the nearest santim - the clinic's favour on a tie,
     * consistent with how the printed price list is produced.
     */
    public function multiply(float $rate): self
    {
        if ($rate < 0) {
            throw new InvalidArgumentException('Rate cannot be negative.');
        }

        return new self((int) round($this->amountMinor * $rate), $this->currency);
    }

    /** The larger of the two, useful for clamping a minimum deposit. */
    public function max(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor >= $other->amountMinor ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinor > 0;
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

    /** Exact decimal string for binding to a DECIMAL(10,2) column. */
    public function toDatabase(): string
    {
        return number_format($this->amountMinor / self::MINOR_UNITS, 2, '.', '');
    }

    /**
     * Human-readable, e.g. "ETB 2,500.00".
     *
     * Formatted by hand rather than via ext-intl, which is commonly missing
     * on Ethiopian shared hosting. Whole amounts drop the decimals because
     * that is how prices are quoted locally.
     */
    public function format(bool $withDecimals = false): string
    {
        $decimals = $withDecimals || ($this->amountMinor % self::MINOR_UNITS !== 0) ? 2 : 0;

        return $this->currency . ' ' . number_format($this->toMajor(), $decimals, '.', ',');
    }

    /** Number only, no currency code - for table cells with a currency header. */
    public function formatBare(bool $withDecimals = false): string
    {
        $decimals = $withDecimals || ($this->amountMinor % self::MINOR_UNITS !== 0) ? 2 : 0;

        return number_format($this->toMajor(), $decimals, '.', ',');
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
