<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Supported interface languages.
 *
 * Amharic uses Ge'ez script, which needs a different font stack and a
 * slightly looser line-height than Latin text; fontClass() carries that
 * decision into the templates so it is made in exactly one place.
 *
 * Afaan Oromo is written in Latin script (Qubee), so it shares English's
 * font stack. It is not a Latin clone in every respect though: Qubee uses
 * doubled vowels and consonants ("hojjetaa", "fayyaa"), which makes words
 * noticeably longer than their English equivalents - layouts must size to
 * the text rather than the other way round.
 */
enum Locale: string
{
    case EN = 'en';
    case AM = 'am';
    case OM = 'om';

    public static function fromRequest(?string $value, self $fallback = self::EN): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? $fallback;
    }

    /** Native name, always shown in its own script. */
    public function nativeLabel(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::AM => "\u{12A0}\u{121B}\u{122D}\u{129B}",
            self::OM => 'Afaan Oromoo',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::EN => 'EN',
            self::AM => "\u{12A0}\u{121B}",
            self::OM => 'OM',
        };
    }

    /** BCP-47 tag for the <html lang> attribute and hreflang links. */
    public function htmlLang(): string
    {
        return match ($this) {
            self::EN => 'en',
            self::AM => 'am-ET',
            self::OM => 'om-ET',
        };
    }

    /**
     * Body class that selects the font stack.
     *
     * Only Ge'ez needs its own stack. Afaan Oromo is Latin script and shares
     * the default one on purpose: giving each language a different typeface
     * would make the three versions read as three different websites.
     */
    public function fontClass(): string
    {
        return match ($this) {
            self::AM        => 'font-ethiopic',
            self::EN, self::OM => 'font-sans',
        };
    }

    /**
     * Suffix for the translated column of a localised row, e.g. name -> name_am.
     * Returns '' for English, which is the base column, so callers can
     * concatenate unconditionally.
     */
    public function columnSuffix(): string
    {
        return $this === self::EN ? '' : '_' . $this->value;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::EN, self::AM, self::OM];
    }
}
