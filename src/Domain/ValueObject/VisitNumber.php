<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The encounter identifier: VIS-YYYYMMDD-XXXXX (FRS 5.5).
 *
 * Sequential per calendar day, from NumberSequence - see PatientId's
 * docblock for why this is not generate-and-check like BookingReference.
 * This value NEVER changes across an OPD-to-IPD conversion (ENC-001);
 * EncounterService reuses the same VisitNumber, it never issues a new one
 * on upgrade.
 */
final readonly class VisitNumber
{
    private const string PREFIX = 'VIS';

    private function __construct(public string $value)
    {
    }

    public static function forDate(DateTimeImmutable $date, int $sequence): self
    {
        if ($sequence < 1 || $sequence > 99999) {
            throw new InvalidArgumentException('Sequence out of range for a visit number.');
        }

        return new self(sprintf('%s-%s-%05d', self::PREFIX, $date->format('Ymd'), $sequence));
    }

    /** @throws InvalidArgumentException when the value is not well-formed */
    public static function fromString(string $value): self
    {
        $normalised = mb_strtoupper(trim($value));

        if (preg_match('/^VIS-(\d{8})-(\d{5})$/', $normalised) !== 1) {
            throw new InvalidArgumentException('That is not a valid visit number.');
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
