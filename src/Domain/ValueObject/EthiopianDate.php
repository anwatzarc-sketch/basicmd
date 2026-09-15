<?php

declare(strict_types=1);

namespace Aster\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Ethiopian (Ge'ez) calendar date.
 *
 * The Ethiopian year has 13 months: twelve of exactly 30 days plus Pagume,
 * a short 13th month of 5 days (6 in a leap year). New Year (Meskerem 1)
 * falls on 11 September Gregorian, or 12 September in the year preceding a
 * Gregorian leap year.
 *
 * Conversion goes through the Julian Day Number so that both directions use
 * one well-defined integer pivot instead of ad-hoc month arithmetic. The
 * ext/calendar functions are deliberately NOT used - that extension is often
 * absent on shared hosting, and this class must never be the reason a page
 * 500s.
 *
 * Patients in Addis Ababa think in this calendar; the clinic's systems and
 * every stored timestamp remain Gregorian/UTC. This class exists purely to
 * render dates, never to store them.
 */
final readonly class EthiopianDate
{
    /**
     * JDN of 1 Meskerem 1 in the Amete Mihret (Year of Mercy) era, the
     * civil reckoning used in Ethiopia today.
     */
    private const int JD_EPOCH_OFFSET_AMETE_MIHRET = 1723856;

    /** @var list<string> Month names in Ge'ez script, Meskerem first. */
    private const array MONTHS_AM = [
        "\u{1218}\u{1235}\u{12A8}\u{1228}\u{121D}",       // Meskerem
        "\u{1325}\u{1245}\u{121D}\u{1275}",               // Tikimt
        "\u{1205}\u{12F3}\u{122D}",                       // Hidar
        "\u{1273}\u{1215}\u{1223}\u{1225}",               // Tahsas
        "\u{1325}\u{122D}",                               // Tir
        "\u{12E8}\u{12AB}\u{1272}\u{1275}",               // Yekatit
        "\u{1218}\u{130B}\u{1262}\u{1275}",               // Megabit
        "\u{121A}\u{12EB}\u{12DD}\u{12EB}",               // Miyazya
        "\u{130D}\u{1295}\u{1266}\u{1275}",               // Ginbot
        "\u{1230}\u{1294}",                               // Sene
        "\u{1210}\u{121D}\u{1208}",                       // Hamle
        "\u{1290}\u{1210}\u{1234}",                       // Nehase
        "\u{1333}\u{1309}\u{121C}\u{1295}",               // Pagume
    ];

    /** @var list<string> Latin transliterations, for the English interface. */
    private const array MONTHS_EN = [
        'Meskerem', 'Tikimt', 'Hidar', 'Tahsas', 'Tir', 'Yekatit', 'Megabit',
        'Miyazya', 'Ginbot', 'Sene', 'Hamle', 'Nehase', 'Pagume',
    ];

    /** @var list<string> Weekday names in Ge'ez, Sunday first. */
    private const array WEEKDAYS_AM = [
        "\u{12A5}\u{1211}\u{12F5}",                       // Ehud
        "\u{1230}\u{129E}",                               // Segno
        "\u{121B}\u{12AD}\u{1230}\u{129E}",               // Maksegno
        "\u{1228}\u{1261}\u{12D5}",                       // Rebue
        "\u{1210}\u{1219}\u{1235}",                       // Hamus
        "\u{12D3}\u{122D}\u{1265}",                       // Arb
        "\u{1245}\u{12F3}\u{121C}",                       // Kidame
    ];

    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($month < 1 || $month > 13) {
            throw new InvalidArgumentException("Ethiopian month must be 1-13, got {$month}.");
        }

        $maxDay = $month === 13 ? (self::isLeapYear($year) ? 6 : 5) : 30;

        if ($day < 1 || $day > $maxDay) {
            throw new InvalidArgumentException(
                "Day {$day} is out of range for Ethiopian month {$month} (max {$maxDay})."
            );
        }
    }

    /**
     * An Ethiopian year is a leap year when year mod 4 == 3; Pagume then
     * carries a sixth day. This falls in the Gregorian year BEFORE a
     * Gregorian leap year, which is why the two never look aligned.
     */
    public static function isLeapYear(int $year): bool
    {
        return $year % 4 === 3;
    }

    public static function fromGregorian(DateTimeImmutable $date): self
    {
        $jdn = self::gregorianToJdn(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return self::fromJdn($jdn);
    }

    public static function fromJdn(int $jdn): self
    {
        $offset = $jdn - self::JD_EPOCH_OFFSET_AMETE_MIHRET;

        // 1461 days = one 4-year Ethiopian cycle (3 common + 1 leap).
        $r = $offset % 1461;
        if ($r < 0) {
            $r += 1461;
        }

        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $year  = 4 * intdiv($offset, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day   = ($n % 30) + 1;

        return new self($year, $month, $day);
    }

    public function toJdn(): int
    {
        return (self::JD_EPOCH_OFFSET_AMETE_MIHRET + 365)
            + 365 * ($this->year - 1)
            + intdiv($this->year, 4)
            + 30 * $this->month
            + $this->day
            - 31;
    }

    public function toGregorian(): DateTimeImmutable
    {
        [$y, $m, $d] = self::jdnToGregorian($this->toJdn());

        $date = DateTimeImmutable::createFromFormat(
            'Y-n-j H:i:s',
            sprintf('%d-%d-%d 00:00:00', $y, $m, $d)
        );

        if ($date === false) {
            throw new InvalidArgumentException('Failed to build Gregorian date from Ethiopian date.');
        }

        return $date;
    }

    /** Proleptic Gregorian calendar -> Julian Day Number. */
    private static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day
            + intdiv(153 * $m + 2, 5)
            + 365 * $y
            + intdiv($y, 4)
            - intdiv($y, 100)
            + intdiv($y, 400)
            - 32045;
    }

    /**
     * Julian Day Number -> proleptic Gregorian calendar.
     *
     * @return array{int, int, int} [year, month, day]
     */
    private static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day   = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year  = 100 * $b + $d - 4800 + intdiv($m, 10);

        return [$year, $month, $day];
    }

    public function monthName(string $locale = 'am'): string
    {
        return $locale === 'am'
            ? self::MONTHS_AM[$this->month - 1]
            : self::MONTHS_EN[$this->month - 1];
    }

    /** Weekday index, 0 = Sunday, derived from the JDN so it needs no lookup. */
    public function weekdayIndex(): int
    {
        return ($this->toJdn() + 1) % 7;
    }

    public function weekdayName(string $locale = 'am'): string
    {
        $index = $this->weekdayIndex();

        if ($locale === 'am') {
            return self::WEEKDAYS_AM[$index];
        }

        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$index];
    }

    /**
     * Long form, e.g. "05 Meskerem 2018" or the Ge'ez equivalent.
     */
    public function format(string $locale = 'am'): string
    {
        return sprintf('%02d %s %d', $this->day, $this->monthName($locale), $this->year);
    }

    /** Compact numeric form, e.g. "05/01/2018". */
    public function formatShort(): string
    {
        return sprintf('%02d/%02d/%d', $this->day, $this->month, $this->year);
    }

    public function __toString(): string
    {
        return $this->format('am');
    }
}
