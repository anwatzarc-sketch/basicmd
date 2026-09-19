<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

/**
 * One analyte in the master test directory (migration 012).
 *
 * Catalogue content, not patient data - units, reference bounds and
 * methodology are properties of the test, so nothing here is encrypted
 * and every one of these fields is safe to render on an administrative
 * listing.
 *
 * referenceMin/referenceMax null means QUALITATIVE: referenceText then
 * carries what "normal" reads as. See migration 012's own note on why
 * that is not modelled as a 0/0 interval.
 */
final readonly class LabPanelParameter
{
    public function __construct(
        public int $id,
        public int $panelId,
        public string $parameterName,
        public string $unit,
        public ?float $referenceMin,
        public ?float $referenceMax,
        public ?string $referenceText,
        public string $methodology,
        public int $sortOrder,
        public bool $isActive,
        /** Denormalised for the directory listing, which groups by panel. */
        public ?string $panelCode = null,
        public ?string $panelName = null,
        public ?string $department = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            panelId:       (int) $row['panel_id'],
            parameterName: (string) $row['parameter_name'],
            unit:          (string) ($row['unit'] ?? ''),
            referenceMin:  isset($row['ref_min']) && $row['ref_min'] !== null ? (float) $row['ref_min'] : null,
            referenceMax:  isset($row['ref_max']) && $row['ref_max'] !== null ? (float) $row['ref_max'] : null,
            referenceText: isset($row['ref_text']) && $row['ref_text'] !== null && $row['ref_text'] !== ''
                               ? (string) $row['ref_text']
                               : null,
            methodology:   (string) ($row['methodology'] ?? ''),
            sortOrder:     (int) ($row['sort_order'] ?? 0),
            isActive:      (bool) ($row['is_active'] ?? true),
            panelCode:     isset($row['panel_code']) ? (string) $row['panel_code'] : null,
            panelName:     isset($row['panel_name']) ? (string) $row['panel_name'] : null,
            department:    isset($row['department']) ? (string) $row['department'] : null,
        );
    }

    public function isQuantitative(): bool
    {
        return $this->referenceMin !== null || $this->referenceMax !== null;
    }

    /**
     * The interval as it is printed - "12 - 16", "up to 34", or the
     * qualitative text. Formatting lives here rather than in three
     * templates so the report, the entry grid and the directory cannot
     * disagree about how an open-ended range reads.
     */
    public function referenceDisplay(): string
    {
        if (!$this->isQuantitative()) {
            return $this->referenceText ?? 'Not defined';
        }

        $min = $this->referenceMin;
        $max = $this->referenceMax;

        if ($min !== null && $max !== null) {
            return self::number($min) . ' - ' . self::number($max);
        }

        return $min !== null
            ? 'Above ' . self::number($min)
            : 'Up to ' . self::number($max ?? 0.0);
    }

    /** Trim the DECIMAL(14,4) tail so 12.0000 prints as 12. */
    public static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
