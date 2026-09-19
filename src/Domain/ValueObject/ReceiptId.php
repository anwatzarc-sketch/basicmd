<?php

declare(strict_types=1);

namespace MediCareMini\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The receivable-payment receipt identifier: RCT-YYYYMMDD-XXXXX (FRS 7.2).
 *
 * Sequential per calendar day via NumberSequence - see PatientId's
 * docblock for why this is not generate-and-check.
 */
final readonly class ReceiptId
{
    private const string PREFIX = 'RCT';

    private function __construct(public string $value)
    {
    }

    public static function forDate(DateTimeImmutable $date, int $sequence): self
    {
        if ($sequence < 1 || $sequence > 99999) {
            throw new InvalidArgumentException('Sequence out of range for a receipt id.');
        }

        return new self(sprintf('%s-%s-%05d', self::PREFIX, $date->format('Ymd'), $sequence));
    }

    /** @throws InvalidArgumentException when the value is not well-formed */
    public static function fromString(string $value): self
    {
        $normalised = mb_strtoupper(trim($value));

        if (preg_match('/^RCT-(\d{8})-(\d{5})$/', $normalised) !== 1) {
            throw new InvalidArgumentException('That is not a valid receipt id.');
        }

        return new self($normalised);
    }

    public static function tryFrom(string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
