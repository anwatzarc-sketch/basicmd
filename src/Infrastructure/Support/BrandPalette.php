<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

/**
 * Pure colour math backing the brand theme: hex validation, hex -> CSS
 * custom-property triple conversion, and deriving a full 50-950 shade ramp
 * from a single primary hex.
 *
 * Kept dependency-free and static so it is trivially unit-testable and safe
 * to call from BrandResolver without risking any I/O or exceptions.
 */
final class BrandPalette
{
    /** Every ramp key, lightest to darkest, in the order Tailwind's scale uses. */
    public const array SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

    /**
     * Fraction of white/black to mix into the primary colour for each shade.
     * Positive values mix toward white (lighter than 500), negative toward
     * black (darker than 500). 500 is the primary colour itself, unmixed.
     */
    private const array MIX = [
        '50'  => 0.95,
        '100' => 0.90,
        '200' => 0.75,
        '300' => 0.60,
        '400' => 0.30,
        '500' => 0.0,
        '600' => -0.15,
        '700' => -0.30,
        '800' => -0.45,
        '900' => -0.60,
        '950' => -0.75,
    ];

    public static function isValidHex(string $value): bool
    {
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value)) === 1;
    }

    /**
     * "#056460" -> "5 100 96", the space-separated triple app.css's
     * rgb(var(--brand-N) / <alpha-value>) utilities expect.
     */
    public static function hexToTriple(string $hex): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        return "{$r} {$g} {$b}";
    }

    /**
     * Derive the 50-950 shade ramp from one primary hex by linearly mixing
     * toward white (lighter shades) or black (darker shades) - the same
     * approach a Sass mix($colour, white, %) / mix($colour, black, %)
     * helper would take.
     *
     * @return array<string, string> shade key => "R G B" triple
     */
    public static function buildRamp(string $primaryHex): array
    {
        [$r, $g, $b] = self::hexToRgb($primaryHex);
        $ramp = [];

        foreach (self::MIX as $shade => $fraction) {
            if ($fraction === 0.0) {
                $ramp[$shade] = "{$r} {$g} {$b}";
                continue;
            }

            $target = $fraction > 0 ? 255 : 0;
            $weight = abs($fraction);

            $mr = (int) round($r + ($target - $r) * $weight);
            $mg = (int) round($g + ($target - $g) * $weight);
            $mb = (int) round($b + ($target - $b) * $weight);

            $ramp[$shade] = self::clamp($mr) . ' ' . self::clamp($mg) . ' ' . self::clamp($mb);
        }

        return $ramp;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function clamp(int $value): int
    {
        return max(0, min(255, $value));
    }
}
