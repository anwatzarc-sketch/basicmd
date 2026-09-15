<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use DateTimeImmutable;

/** A medication instruction tied to an encounter (FRS 6.3). */
final readonly class EPrescription
{
    public function __construct(
        public int $id,
        public int $encounterId,
        public int $prescriberId,
        public string $icdCode,
        public string $medicationName,
        public string $dosage,
        public string $frequency,
        public int $durationDays,
        public bool $isDispensed,
        public DateTimeImmutable $createdAt,
        public ?string $prescriberName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            encounterId:     (int) $row['encounter_id'],
            prescriberId:    (int) $row['prescriber_id'],
            icdCode:         (string) $row['icd_code'],
            medicationName:  (string) $row['medication_name'],
            dosage:          (string) $row['dosage'],
            frequency:       (string) $row['frequency'],
            durationDays:    (int) $row['duration_days'],
            isDispensed:     (bool) ($row['is_dispensed'] ?? false),
            createdAt:       self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            prescriberName:  isset($row['prescriber_name']) ? (string) $row['prescriber_name'] : null,
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    /** "500mg - twice daily - 7 days" */
    public function instructionSummary(): string
    {
        return sprintf('%s - %s - %d day%s', $this->dosage, $this->frequency, $this->durationDays, $this->durationDays === 1 ? '' : 's');
    }
}
