<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Support;

/**
 * Code 39 symbology, as bar geometry.
 *
 * A REAL barcode, not a decorative one. The printed report carries the
 * specimen label so that a technician can scan the sheet back to the
 * order; a row of pretty stripes that no scanner reads would be worse
 * than printing nothing, because it invites someone to try.
 *
 * Code 39 is the right symbology here for boring reasons: it encodes the
 * digits, capitals and hyphen that AccessionNumber is made of and nothing
 * else, it needs no check digit, and every handheld scanner made in the
 * last forty years reads it out of the box. It is not dense - that costs
 * width on the page, and a lab label has width to spare.
 *
 * This returns geometry (x offsets and widths in abstract units) rather
 * than markup, so the template that draws it owns the escaping, as every
 * template in this application does.
 */
final class Code39
{
    /**
     * Nine elements per character - bar, space, bar, space, ... starting
     * and ending on a bar - where n is one unit wide and w is three.
     * Exactly three of the nine are wide, which is what makes the
     * symbology self-checking on element count.
     */
    private const array PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn', // start/stop delimiter, never part of the data
    ];

    private const int NARROW = 1;
    private const int WIDE   = 3;

    /** Narrow units of quiet zone at each end - the spec's own minimum. */
    private const int QUIET_ZONE = 10;

    /**
     * Bars for $value, as {x, width} pairs in narrow units, plus the total
     * width so the caller can set a viewBox.
     *
     * Characters the symbology cannot express are DROPPED rather than
     * substituted: a barcode that silently scans as a different string
     * than the one printed beneath it is the one failure mode worth
     * engineering against here.
     *
     * @return array{bars: list<array{x: int, width: int}>, width: int, value: string}
     */
    public static function bars(string $value): array
    {
        $normalised = '';

        foreach (mb_str_split(mb_strtoupper(trim($value))) as $character) {
            if ($character !== '*' && isset(self::PATTERNS[$character])) {
                $normalised .= $character;
            }
        }

        $bars   = [];
        $cursor = self::QUIET_ZONE;

        if ($normalised === '') {
            return ['bars' => [], 'width' => 0, 'value' => ''];
        }

        foreach (mb_str_split('*' . $normalised . '*') as $index => $character) {
            if ($index > 0) {
                // One narrow space between characters.
                $cursor += self::NARROW;
            }

            foreach (mb_str_split(self::PATTERNS[$character]) as $element => $size) {
                $width = $size === 'w' ? self::WIDE : self::NARROW;

                // Even elements are bars, odd are spaces.
                if ($element % 2 === 0) {
                    $bars[] = ['x' => $cursor, 'width' => $width];
                }

                $cursor += $width;
            }
        }

        return [
            'bars'  => $bars,
            'width' => $cursor + self::QUIET_ZONE,
            'value' => $normalised,
        ];
    }

    /** Whether $value can be encoded at all (after dropping unknowns). */
    public static function isEncodable(string $value): bool
    {
        return self::bars($value)['value'] !== '';
    }
}
