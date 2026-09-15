<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use InvalidArgumentException;
use Random\RandomException;

/**
 * The patient-facing booking reference, e.g. "AMC-7F3K9Q2B".
 *
 * This is the only identifier a patient ever sees or types: it goes in the
 * confirmation email, on the bank transfer reason field, and into the
 * "check my booking" lookup. Two consequences drive the design:
 *
 *  1. It must be unguessable, because the lookup page is unauthenticated.
 *     Sequential ids would let anyone enumerate other patients' bookings.
 *  2. It must survive being read aloud over the phone and copied off a bank
 *     slip, so the alphabet excludes every character pair that gets confused
 *     in handwriting: 0/O, 1/I/L, 5/S, 8/B, 2/Z.
 *
 * Exactly 12 characters, matching the CHAR(12) column.
 */
final readonly class BookingReference
{
    private const string PREFIX   = 'AMC-';
    private const int    BODY_LEN = 8;

    /** Unambiguous alphabet: no 0, O, 1, I, L, 5, S, 8, B, 2, Z. */
    private const string ALPHABET = '34679ACDEFGHJKMNPQRTUVWXY';

    private function __construct(public string $value)
    {
    }

    /**
     * @throws RandomException if the platform CSPRNG is unavailable, which
     *                         must fail loudly rather than fall back to a
     *                         predictable generator.
     */
    public static function generate(): self
    {
        $alphabetLength = strlen(self::ALPHABET);
        $body           = '';

        for ($i = 0; $i < self::BODY_LEN; $i++) {
            $body .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return new self(self::PREFIX . $body);
    }

    /**
     * Rebuild from stored or user-supplied text.
     *
     * Accepts sloppy input - lowercase, missing prefix, stray spaces or
     * dashes - because patients retype this from a printed slip.
     *
     * @throws InvalidArgumentException when the value cannot be a reference.
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper(preg_replace('/[\s\-]+/', '', $value) ?? '');

        // Tolerate the prefix being present, absent, or duplicated.
        $prefixBare = rtrim(self::PREFIX, '-');
        if (str_starts_with($normalised, $prefixBare)) {
            $normalised = substr($normalised, strlen($prefixBare));
        }

        if (preg_match('/^[' . self::ALPHABET . ']{' . self::BODY_LEN . '}$/', $normalised) !== 1) {
            throw new InvalidArgumentException('That booking reference is not valid.');
        }

        return new self(self::PREFIX . $normalised);
    }

    public static function tryFrom(string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Spaced for readability in emails, e.g. "AMC-7F3K 9Q2B". */
    public function formatted(): string
    {
        $body = substr($this->value, strlen(self::PREFIX));

        return self::PREFIX . substr($body, 0, 4) . ' ' . substr($body, 4);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
