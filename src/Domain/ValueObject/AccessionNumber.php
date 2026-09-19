<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The laboratory accession identifier: LAB-YYYYMMDD-XXXXX.
 *
 * Sequential per calendar day, issued from NumberSequence for exactly the
 * reason VisitNumber and ReceiptId are - see VisitNumber's docblock. It
 * is what the specimen label, the work queue and the printed report all
 * quote, so a technician holding a tube and a printout can prove they
 * belong together without opening the patient record.
 *
 * Deliberately distinct from the order's database id: the id is an
 * implementation detail that leaks row counts, and it is not what anyone
 * writes on a tube.
 */
final readonly class AccessionNumber
{
    private const string PREFIX = 'LAB';

    private function __construct(public string $value)
    {
    }

    public static function forDate(DateTimeImmutable $date, int $sequence): self
    {
        if ($sequence < 1 || $sequence > 99999) {
            throw new InvalidArgumentException('Sequence out of range for an accession number.');
        }

        return new self(sprintf('%s-%s-%05d', self::PREFIX, $date->format('Ymd'), $sequence));
    }

    /** The NumberSequence scope this date's accession numbers count within. */
    public static function scopeFor(DateTimeImmutable $date): string
    {
        return 'accession:' . $date->format('Ymd');
    }

    /** @throws InvalidArgumentException when the value is not well-formed */
    public static function fromString(string $value): self
    {
        $normalised = mb_strtoupper(trim($value));

        if (preg_match('/^LAB-(\d{8})-(\d{5})$/', $normalised) !== 1) {
            throw new InvalidArgumentException('That is not a valid accession number.');
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
