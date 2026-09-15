<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use InvalidArgumentException;

/**
 * The Master Patient Index identifier: PID-YYYY-XXXXX (FRS 5.2).
 *
 * Unlike BookingReference (random, generate-and-check against a unique
 * index), a PID is sequential within its year and comes from
 * NumberSequence - "collision-safe" here means atomic database
 * get-and-increment, not high entropy. This class is a formatted,
 * validated wrapper around that number; it does not generate one itself.
 */
final readonly class PatientId
{
    private const string PREFIX = 'PID';

    private function __construct(public string $value)
    {
    }

    /** Build from an atomically-issued sequence number. */
    public static function forYear(int $year, int $sequence): self
    {
        if ($year < 2000 || $year > 2999) {
            throw new InvalidArgumentException('Year out of range for a patient id.');
        }

        if ($sequence < 1 || $sequence > 99999) {
            throw new InvalidArgumentException('Sequence out of range for a patient id.');
        }

        return new self(sprintf('%s-%04d-%05d', self::PREFIX, $year, $sequence));
    }

    /**
     * Rebuild from stored or user-supplied text.
     *
     * @throws InvalidArgumentException when the value is not a well-formed PID
     */
    public static function fromString(string $value): self
    {
        $normalised = mb_strtoupper(trim($value));

        if (preg_match('/^PID-(\d{4})-(\d{5})$/', $normalised) !== 1) {
            throw new InvalidArgumentException('That is not a valid patient id.');
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
