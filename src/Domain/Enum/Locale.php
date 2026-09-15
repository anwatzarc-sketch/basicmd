<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Supported interface languages.
 *
 * Amharic uses Ge'ez script, which needs a different font stack and a
 * slightly looser line-height than Latin text; fontClass() carries that
 * decision into the templates so it is made in exactly one place.
 */
enum Locale: string
{
    case EN = 'en';
    case AM = 'am';

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
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::EN => 'EN',
            self::AM => "\u{12A0}\u{121B}",
        };
    }

    /** BCP-47 tag for the <html lang> attribute and hreflang links. */
    public function htmlLang(): string
    {
        return match ($this) {
            self::EN => 'en',
            self::AM => 'am-ET',
        };
    }

    /** Body class that swaps in the Ethiopic font stack. */
    public function fontClass(): string
    {
        return match ($this) {
            self::EN => 'font-sans',
            self::AM => 'font-ethiopic',
        };
    }

    /**
     * Suffix for the Amharic column of a bilingual row, e.g. name -> name_am.
     * Returns '' for English so callers can concatenate unconditionally.
     */
    public function columnSuffix(): string
    {
        return $this === self::AM ? '_am' : '';
    }

    public function opposite(): self
    {
        return $this === self::EN ? self::AM : self::EN;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::EN, self::AM];
    }
}
