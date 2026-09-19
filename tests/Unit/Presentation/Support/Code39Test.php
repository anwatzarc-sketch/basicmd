<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Unit\Presentation\Support;

use MediCareMini\Presentation\Support\Code39;
use PHPUnit\Framework\TestCase;

/**
 * The specimen barcode on a printed report is meant to be scanned. These
 * pin the structural properties a scanner actually relies on - if one of
 * them breaks, the bars still look like a barcode and simply stop
 * reading, which is the failure that would otherwise reach the bench.
 */
final class Code39Test extends TestCase
{
    public function test_encodes_an_accession_number(): void
    {
        $symbol = Code39::bars('LAB-20260919-00001');

        self::assertSame('LAB-20260919-00001', $symbol['value']);
        self::assertNotSame([], $symbol['bars']);
        self::assertGreaterThan(0, $symbol['width']);
    }

    public function test_every_character_contributes_five_bars_plus_two_delimiters(): void
    {
        // Code 39 draws 5 bars per character, and the symbol is wrapped in
        // a start and a stop character.
        $symbol = Code39::bars('AB12');

        self::assertCount((4 + 2) * 5, $symbol['bars']);
    }

    public function test_bars_never_overlap_and_advance_left_to_right(): void
    {
        $symbol = Code39::bars('LAB-20260919-00001');
        $cursor = -1;

        foreach ($symbol['bars'] as $bar) {
            self::assertGreaterThan($cursor, $bar['x'], 'bars must not overlap');
            self::assertContains($bar['width'], [1, 3], 'a bar is narrow or wide, nothing else');
            $cursor = $bar['x'] + $bar['width'] - 1;
        }

        self::assertLessThan($symbol['width'], $cursor, 'the quiet zone must survive at the right edge');
    }

    public function test_lowercase_is_normalised_rather_than_dropped(): void
    {
        self::assertSame('LAB-1', Code39::bars('lab-1')['value']);
    }

    public function test_characters_the_symbology_cannot_express_are_dropped(): void
    {
        // Silently encoding something different from the text printed
        // underneath is the one failure worth engineering against, so an
        // unsupported character is removed from both.
        self::assertSame('ABC', Code39::bars('A*B#C')['value']);
        self::assertSame('', Code39::bars('###')['value']);
        self::assertSame([], Code39::bars('###')['bars']);
    }

    public function test_an_empty_value_produces_no_symbol(): void
    {
        $symbol = Code39::bars('');

        self::assertSame([], $symbol['bars']);
        self::assertSame(0, $symbol['width']);
        self::assertFalse(Code39::isEncodable(''));
        self::assertTrue(Code39::isEncodable('LAB-1'));
    }
}
