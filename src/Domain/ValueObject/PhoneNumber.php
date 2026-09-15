<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use InvalidArgumentException;

/**
 * An Ethiopian telephone number, normalised to E.164 (+251XXXXXXXXX).
 *
 * Patients type their number every way imaginable: "0911123456",
 * "+251 911 123 456", "251911123456", "911-123-456". All of those are the
 * same person, and the booking system must treat them as such - the
 * duplicate-booking guard and the reminder engine both key on this value.
 *
 * Ethiopian national significant numbers are 9 digits after the 251 country
 * code, always starting with 9 or 7 (mobile) or 1 through 5 (fixed line).
 */
final readonly class PhoneNumber
{
    private const string COUNTRY_CODE = '251';

    private function __construct(
        /** Normalised E.164 form, e.g. "+251911123456". */
        public string $e164,
        /** The 9-digit national significant number, e.g. "911123456". */
        public string $national,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the input cannot be a valid
     *                                  Ethiopian number.
     */
    public static function fromString(string $input): self
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Phone number is empty.');
        }

        $national = self::extractNationalNumber($digits);

        if ($national === null) {
            throw new InvalidArgumentException(
                'Enter a valid Ethiopian phone number, for example 0911 123 456.'
            );
        }

        return new self('+' . self::COUNTRY_CODE . $national, $national);
    }

    /** Null-returning variant for validation paths that collect many errors. */
    public static function tryFrom(string $input): ?self
    {
        try {
            return self::fromString($input);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(string $input): bool
    {
        return self::tryFrom($input) !== null;
    }

    /**
     * Reduce any accepted spelling to the 9-digit national number.
     * Returns null when the input cannot be one.
     */
    private static function extractNationalNumber(string $digits): ?string
    {
        // "251911123456" / "00251911123456" -> strip the country code.
        if (str_starts_with($digits, '00' . self::COUNTRY_CODE)) {
            $digits = substr($digits, 2 + strlen(self::COUNTRY_CODE));
        } elseif (str_starts_with($digits, self::COUNTRY_CODE) && strlen($digits) === 12) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            // National trunk form "0911123456".
            $digits = substr($digits, 1);
        }

        // Must now be exactly 9 digits with a valid leading digit.
        // 9 and 7 are mobile; 1-5 are regional fixed lines.
        if (preg_match('/^[1-57-9]\d{8}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    /** True for mobile numbers, which are the ones worth texting or calling. */
    public function isMobile(): bool
    {
        return in_array($this->national[0], ['9', '7'], true);
    }

    /** Local display form, e.g. "0911 123 456". */
    public function formatNational(): string
    {
        return '0' . substr($this->national, 0, 3)
            . ' ' . substr($this->national, 3, 3)
            . ' ' . substr($this->national, 6, 3);
    }

    /** International display form, e.g. "+251 911 123 456". */
    public function formatInternational(): string
    {
        return '+' . self::COUNTRY_CODE
            . ' ' . substr($this->national, 0, 3)
            . ' ' . substr($this->national, 3, 3)
            . ' ' . substr($this->national, 6, 3);
    }

    /** tel: href target. */
    public function toTelLink(): string
    {
        return 'tel:' . $this->e164;
    }

    /**
     * Partially masked, e.g. "+251 91* *** *56".
     * Used in any list a non-privileged role can see, so a screenshot of the
     * admin queue does not leak a full patient contact number.
     */
    public function masked(): string
    {
        return '+' . self::COUNTRY_CODE . ' ' . substr($this->national, 0, 2)
            . '* *** *' . substr($this->national, -2);
    }

    public function equals(self $other): bool
    {
        return $this->e164 === $other->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
