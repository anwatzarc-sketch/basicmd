<?php

declare(strict_types=1);

namespace MediCareMini\Domain\ValueObject;

/**
 * Resolved brand identity and colour theme for the current request.
 *
 * Always fully populated - every property has a usable default baked in by
 * BrandResolver, so a template can read any getter unconditionally. The only
 * two properties that are ever null are logoImage and heroImage, because
 * "no image configured" is a real, meaningful state templates branch on.
 *
 * Colour properties that feed CSS custom properties are stored as
 * space-separated "R G B" triples (e.g. "5 100 96") rather than hex, which is
 * the format app.css's rgb(var(--brand-N) / <alpha-value>) utilities expect.
 */
final readonly class CompanyBrand
{
    /** @param array<string, string> $primaryRamp shade key ("50".."950") => "R G B" triple */
    public function __construct(
        public string $businessName,
        public string $businessInitials,
        public string $mainCity,
        public ?string $logoImage,
        public ?string $heroImage,
        public array $primaryRamp,
        public string $secondaryColor,
        public string $backgroundColor,
        public string $surface,
        public float $transparency,
        public string $headerText,
        public string $bodyText,
        public string $footerBackground,
        public string $footerText,
    ) {
    }

    /** The "R G B" triple for one shade of the primary ramp, e.g. primary('700'). */
    public function primary(string $shade): string
    {
        return $this->primaryRamp[$shade] ?? $this->primaryRamp['500'] ?? '15 143 137';
    }
}
