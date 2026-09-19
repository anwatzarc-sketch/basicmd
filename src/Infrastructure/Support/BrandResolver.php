<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

use Aster\Domain\ValueObject\CompanyBrand;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Throwable;

/**
 * Reads storage/CompanyBrand.json and resolves it into a CompanyBrand value
 * object, layering valid overrides on top of today's shipped defaults.
 *
 * Lives under storage/, not public/: a redeploy replaces public/ wholesale
 * (see README's "Deploying to Plesk" notes) but never touches storage/, so
 * an admin-saved brand must live there or it gets silently wiped on every
 * release. It is not web-reachable from storage/, which is also stricter
 * than the old public/ location (it carried no secret data either way).
 *
 * Because it can be hand-edited or corrupted by a failed write, this class
 * treats ANY problem - a missing file, invalid JSON, a wrong type, an
 * invalid hex, an out-of-range transparency, an image path that escapes the
 * media root, or an unrecognised key - as "ignore that one property, fall
 * back to its default". It never throws: a broken brand file must never be
 * able to take the whole site down.
 */
final class BrandResolver
{
    // Today's shipped defaults, verbatim from tailwind.config.js's
    // colors.medical ramp and resources/css/app.css's :root block, so a
    // missing or empty CompanyBrand.json renders byte-identical to today.
    private const string DEFAULT_PRIMARY_700 = '#056460';
    private const string DEFAULT_SECONDARY   = '#d99a32';
    private const string DEFAULT_BACKGROUND  = 'linear-gradient(135deg, #e0f2fe 0%, #f1f5f9 50%, #e2e8f0 100%)';
    private const string DEFAULT_SURFACE     = 'rgba(255, 255, 255, .65)';
    private const string DEFAULT_TEXT_MAIN   = '#0f172a';
    private const string DEFAULT_FOOTER_BG   = '#032423';
    private const string DEFAULT_FOOTER_TEXT = '#5eead4'; // Tailwind's default teal-300.

    /** @var array<string, string> shade key => "R G B" triple */
    private const array DEFAULT_PRIMARY_RAMP = [
        '50'  => '239 252 251',
        '100' => '216 247 244',
        '200' => '179 237 232',
        '300' => '130 222 215',
        '400' => '75 196 189',
        '500' => '15 143 137',
        '600' => '8 123 119',
        '700' => '5 100 96',
        '800' => '12 74 71',
        '900' => '6 59 58',
        '950' => '3 36 35',
    ];

    private ?CompanyBrand $resolved = null;

    public function __construct(
        private readonly Config $config,
        private readonly SettingsRepository $settings,
    ) {
    }

    /** Resolved once per request and memoised; safe to call repeatedly. */
    public function resolve(): CompanyBrand
    {
        return $this->resolved ??= $this->build();
    }

    private function build(): CompanyBrand
    {
        $raw = $this->readJson();

        $identity = is_array($raw['identity'] ?? null) ? $raw['identity'] : [];
        $theme    = is_array($raw['theme'] ?? null) ? $raw['theme'] : [];

        $primaryRamp = self::DEFAULT_PRIMARY_RAMP;

        $primaryHex = $this->validHex($theme['primarycolor'] ?? null);
        if ($primaryHex !== null) {
            $primaryRamp = BrandPalette::buildRamp($primaryHex);
        }

        // Resolved first: the footer accent is checked for contrast against it.
        $footerBackground = $this->hexOrDefault($theme['footerbackground'] ?? null, self::DEFAULT_FOOTER_BG);

        return new CompanyBrand(
            businessName:      $this->validString($identity['businessName'] ?? null)
                ?? $this->settings->string('clinic_name', $this->config->appName),
            businessInitials:  $this->validInitials($identity['businessInitials'] ?? null) ?? 'A',
            mainCity:          $this->validString($identity['mainCity'] ?? null) ?? 'Addis Ababa',
            logoImage:         $this->validMediaPath($identity['logoImage'] ?? null),
            heroImage:         $this->validMediaPath($identity['heroImage'] ?? null),
            primaryRamp:       $primaryRamp,
            secondaryColor:    $this->hexOrDefault($theme['secondarycolor'] ?? null, self::DEFAULT_SECONDARY),
            backgroundColor:   $this->cssValueOrDefault($theme['backgroundcolor'] ?? null, self::DEFAULT_BACKGROUND),
            surface:           $this->cssValueOrDefault($theme['surface'] ?? null, self::DEFAULT_SURFACE),
            transparency:      $this->validTransparency($theme['transparency'] ?? null) ?? 1.0,
            headerText:        $this->hexOrDefault($theme['headertext'] ?? null, self::DEFAULT_TEXT_MAIN),
            bodyText:          $this->hexOrDefault($theme['bodytext'] ?? null, self::DEFAULT_TEXT_MAIN),
            footerBackground:  $footerBackground,
            footerText:        $this->legibleFooterText($theme['footertext'] ?? null, $footerBackground),
        );
    }

    /**
     * The footer accent, but never one that disappears into the footer.
     *
     * The colour picker will happily accept near-black footer text on a
     * near-black footer, and it did: the live brand file set #161d1c against
     * a #032423 footer, which rendered the "Explore" and "Contact" headings
     * and the emergency phone number as invisible. Anything below WCAG AA for
     * body text falls back to the shipped accent rather than being drawn.
     *
     * This is the same stance the rest of this class takes - an administrator
     * must not be able to type the public site into being unreadable.
     */
    private function legibleFooterText(mixed $value, string $footerBackground): string
    {
        $candidate = $this->hexOrDefault($value, self::DEFAULT_FOOTER_TEXT);

        if (BrandPalette::contrastRatio($candidate, $footerBackground) >= 4.5) {
            return $candidate;
        }

        $fallback = BrandPalette::hexToTriple(self::DEFAULT_FOOTER_TEXT);

        // If even the shipped accent fails - a footer background close to the
        // default teal - fall back to white, which always clears a dark bar.
        return BrandPalette::contrastRatio($fallback, $footerBackground) >= 4.5
            ? $fallback
            : '255 255 255';
    }

    /** @return array<string, mixed> */
    private function readJson(): array
    {
        try {
            $path = $this->path();

            if (!is_file($path)) {
                return [];
            }

            $contents = file_get_contents($path);

            if ($contents === false || trim($contents) === '') {
                return [];
            }

            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            // Malformed JSON, unreadable file, whatever - defaults win.
            return [];
        }
    }

    public function path(): string
    {
        return $this->config->path('storage/CompanyBrand.json');
    }

    private function validString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 200);
    }

    private function validInitials(mixed $value): ?string
    {
        $trimmed = $this->validString($value);

        return $trimmed === null ? null : mb_substr($trimmed, 0, 3);
    }

    private function validHex(mixed $value): ?string
    {
        if (!is_string($value) || !BrandPalette::isValidHex($value)) {
            return null;
        }

        return trim($value);
    }

    private function hexOrDefault(mixed $value, string $defaultHex): string
    {
        $hex = $this->validHex($value);

        return BrandPalette::hexToTriple($hex ?? $defaultHex);
    }

    /**
     * backgroundColor/surface carry raw CSS values (a gradient, an rgba()
     * colour) rather than a single hex, so they cannot be run through
     * hexToTriple. Still guarded against breaking out of the nonced <style>
     * block they are emitted into.
     */
    private function cssValueOrDefault(mixed $value, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || mb_strlen($trimmed) > 300) {
            return $default;
        }

        if (preg_match('/[<>{};]/', $trimmed) === 1) {
            return $default;
        }

        return $trimmed;
    }

    private function validTransparency(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $float = (float) $value;

        return ($float >= 0.0 && $float <= 1.0) ? $float : null;
    }

    /**
     * Accept an image path only if it resolves to an existing file inside
     * the media root - defence against a hand-edited JSON file pointing
     * `logoImage` at `../../.env` or similar.
     */
    private function validMediaPath(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $relative = ltrim(trim($value), '/');
        $root     = realpath($this->config->mediaDir());

        if ($root === false) {
            return null;
        }

        $absolute = realpath($this->config->mediaDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($absolute === false || !str_starts_with($absolute, $root) || !is_file($absolute)) {
            return null;
        }

        return $relative;
    }
}
