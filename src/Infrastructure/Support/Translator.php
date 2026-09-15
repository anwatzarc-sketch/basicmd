<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

use Aster\Domain\Enum\Locale;
use Aster\Domain\ValueObject\EthiopianDate;
use Aster\Domain\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Translation and locale-aware formatting.
 *
 * Dictionaries are plain PHP arrays, so OPcache keeps them compiled in memory
 * and lookup costs nothing at runtime - no parsing, no database, no gettext.
 *
 * A missing key falls back to English and then to the key itself, never to a
 * blank string: a visible "booking.submit" tells a translator exactly what to
 * fix, whereas an empty button tells nobody anything.
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> locale => dictionary */
    private array $dictionaries = [];

    /** @var list<string> keys requested but not found, surfaced in debug mode */
    private array $missing = [];

    public function __construct(
        private readonly string $langPath,
        private Locale $locale = Locale::EN,
        private readonly DateTimeZone $timezone = new DateTimeZone('Africa/Addis_Ababa'),
        private readonly bool $collectMissing = false,
    ) {
        $this->load($this->locale);
        $this->load(Locale::EN); // Fallback dictionary is always resident.
    }

    private function load(Locale $locale): void
    {
        if (isset($this->dictionaries[$locale->value])) {
            return;
        }

        $file = $this->langPath . DIRECTORY_SEPARATOR . $locale->value . '.php';

        $this->dictionaries[$locale->value] = is_readable($file) ? (array) require $file : [];
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
        $this->load($locale);
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function isAmharic(): bool
    {
        return $this->locale === Locale::AM;
    }

    /**
     * Resolve a dot-notated key, substituting :placeholders.
     *
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        $value = $this->lookup($key, $this->locale);

        if ($value === null && $this->locale !== Locale::EN) {
            $value = $this->lookup($key, Locale::EN);
        }

        if ($value === null) {
            if ($this->collectMissing) {
                $this->missing[$key] = $key;
            }

            return $key;
        }

        if ($replacements === []) {
            return $value;
        }

        $pairs = [];

        foreach ($replacements as $name => $replacement) {
            $pairs[':' . $name] = (string) $replacement;
        }

        return strtr($value, $pairs);
    }

    /** Shorthand alias used throughout the templates. */
    public function t(string $key, array $replacements = []): string
    {
        return $this->get($key, $replacements);
    }

    /** Translate and HTML-escape in one call - the safe default in views. */
    public function e(string $key, array $replacements = []): string
    {
        return htmlspecialchars($this->get($key, $replacements), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function has(string $key): bool
    {
        return $this->lookup($key, $this->locale) !== null
            || $this->lookup($key, Locale::EN) !== null;
    }

    private function lookup(string $key, Locale $locale): ?string
    {
        $segments = explode('.', $key);
        $node     = $this->dictionaries[$locale->value] ?? [];

        foreach ($segments as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return is_string($node) ? $node : null;
    }

    /** @return list<string> */
    public function missingKeys(): array
    {
        return array_values($this->missing);
    }

    // -----------------------------------------------------------------
    //  Locale-aware formatting
    // -----------------------------------------------------------------

    /**
     * Format a date for display.
     *
     * In Amharic the Ethiopian calendar leads with the Gregorian date in
     * parentheses. Patients think in Ge'ez dates, but every bank slip, lab
     * report and ID card they will be handed is Gregorian, so showing only
     * one of the two causes real confusion at the front desk.
     */
    public function date(DateTimeImmutable $date, bool $withWeekday = false): string
    {
        $local = $date->setTimezone($this->timezone);

        if ($this->locale === Locale::AM) {
            $ethiopian = EthiopianDate::fromGregorian($local);

            $formatted = $ethiopian->format('am');

            if ($withWeekday) {
                $formatted = $ethiopian->weekdayName('am') . '፣ ' . $formatted;
            }

            return $formatted . ' (' . $local->format('d M Y') . ')';
        }

        return $withWeekday ? $local->format('D, d M Y') : $local->format('d M Y');
    }

    /** Short numeric date, for dense admin tables. */
    public function dateShort(DateTimeImmutable $date): string
    {
        return $date->setTimezone($this->timezone)->format('d/m/Y');
    }

    public function dateTime(DateTimeImmutable $date): string
    {
        $local = $date->setTimezone($this->timezone);

        return $this->date($local) . ' ' . $local->format('H:i');
    }

    public function time(DateTimeImmutable $date): string
    {
        return $date->setTimezone($this->timezone)->format('H:i');
    }

    /**
     * Relative phrasing for recent timestamps ("2 hours ago").
     *
     * Falls back to an absolute date beyond a week, where "37 days ago" stops
     * being easier to read than the date itself.
     */
    public function relative(DateTimeImmutable $date): string
    {
        $now     = new DateTimeImmutable('now', $this->timezone);
        $seconds = $now->getTimestamp() - $date->getTimestamp();

        if ($seconds < 0) {
            return $this->date($date);
        }

        $isAm = $this->locale === Locale::AM;

        return match (true) {
            $seconds < 60      => $isAm ? 'አሁን' : 'just now',
            $seconds < 3600    => $this->plural(intdiv($seconds, 60), 'minute'),
            $seconds < 86400   => $this->plural(intdiv($seconds, 3600), 'hour'),
            $seconds < 604800  => $this->plural(intdiv($seconds, 86400), 'day'),
            default            => $this->date($date),
        };
    }

    private function plural(int $count, string $unit): string
    {
        if ($this->locale === Locale::AM) {
            $word = match ($unit) {
                'minute' => 'ደቂቃ',
                'hour'   => 'ሰዓት',
                default  => 'ቀን',
            };

            return "ከ{$count} {$word} በፊት";
        }

        $word = $count === 1 ? $unit : $unit . 's';

        return "{$count} {$word} ago";
    }

    /**
     * Money, with the currency word localised.
     *
     * ETB reads as "ብር" in Amharic, which is what appears on every price list
     * and receipt in the country.
     */
    public function money(Money $amount, bool $withDecimals = false): string
    {
        $number = $amount->formatBare($withDecimals);

        return $this->locale === Locale::AM
            ? $number . ' ' . $this->get('common.etb')
            : $amount->currency . ' ' . $number;
    }

    /** Percentage, e.g. 0.20 -> "20%". */
    public function percent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 1, '.', ''), '0'), '.') . '%';
    }

    /** Relative day name where one exists, otherwise a formatted date. */
    public function dayLabel(DateTimeImmutable $date): string
    {
        $today = new DateTimeImmutable('today', $this->timezone);
        $local = $date->setTimezone($this->timezone);

        return match ($local->format('Y-m-d')) {
            $today->format('Y-m-d')                        => $this->get('common.today'),
            $today->modify('+1 day')->format('Y-m-d')      => $this->get('common.tomorrow'),
            default                                        => $this->date($local, true),
        };
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
