<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\DiagnosticCategory;
use Aster\Domain\Enum\DiagnosticStatus;
use Aster\Domain\Enum\Gender;
use Aster\Domain\ValueObject\AccessionNumber;
use DateTimeImmutable;

/**
 * A lab/imaging/PACS request tied to an encounter (FRS 6.2).
 *
 * resultsPayloadEncrypted carries CIPHERTEXT - see ClinicalNote's docblock
 * for the same boundary and why. For a Lab order that ciphertext decodes
 * to a LabReport (migration 012); LabReportService is the only thing that
 * does so.
 *
 * The specimen block (accession number, type, barcode, collection time,
 * clinical location) and the result-authorship pair are lab facts about
 * an order and are NULL on every Imaging/PACS order and on every order
 * placed before migration 012 - templates branch on that rather than
 * assuming a laboratory workflow.
 */
final readonly class DiagnosticOrder
{
    public function __construct(
        public int $id,
        public int $encounterId,
        public int $orderingPhysicianId,
        public DiagnosticCategory $category,
        public string $testCode,
        public string $testName,
        public string $icdCode,
        public DiagnosticStatus $status,
        public ?string $resultsPayloadEncrypted,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $physicianName = null,
        // --- Laboratory specimen block (migration 012) -----------------
        public ?AccessionNumber $accessionNumber = null,
        public ?string $panelCode = null,
        public ?string $specimenType = null,
        public ?string $specimenBarcode = null,
        public ?DateTimeImmutable $collectedAt = null,
        public ?string $clinicalLocation = null,
        public ?int $resultedById = null,
        public ?DateTimeImmutable $resultedAt = null,
        // --- Denormalised for the work queue, never persisted ----------
        public ?string $resultedByName = null,
        public ?int $patientId = null,
        public ?string $patientName = null,
        public ?string $patientPid = null,
        public ?string $patientGender = null,
        public ?DateTimeImmutable $patientDateOfBirth = null,
        public ?string $visitNumber = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                      (int) $row['id'],
            encounterId:             (int) $row['encounter_id'],
            orderingPhysicianId:     (int) $row['ordering_physician_id'],
            category:                DiagnosticCategory::from((string) $row['category']),
            testCode:                (string) $row['test_code'],
            testName:                (string) $row['test_name'],
            icdCode:                 (string) $row['icd_code'],
            status:                  DiagnosticStatus::from((string) $row['status']),
            resultsPayloadEncrypted: self::nullableString($row['results_payload_encrypted'] ?? null),
            createdAt:               self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            updatedAt:               self::toDate($row['updated_at'] ?? null) ?? new DateTimeImmutable(),
            physicianName:           isset($row['physician_name']) ? (string) $row['physician_name'] : null,
            accessionNumber:         self::toAccession($row['accession_number'] ?? null),
            panelCode:               self::nullableString($row['panel_code'] ?? null),
            specimenType:            self::nullableString($row['specimen_type'] ?? null),
            specimenBarcode:         self::nullableString($row['specimen_barcode'] ?? null),
            collectedAt:             self::toDate($row['collected_at'] ?? null),
            clinicalLocation:        self::nullableString($row['clinical_location'] ?? null),
            resultedById:            isset($row['resulted_by_id']) && $row['resulted_by_id'] !== null
                                          ? (int) $row['resulted_by_id']
                                          : null,
            resultedAt:              self::toDate($row['resulted_at'] ?? null),
            resultedByName:          self::nullableString($row['resulted_by_name'] ?? null),
            patientId:               isset($row['patient_id']) && $row['patient_id'] !== null
                                          ? (int) $row['patient_id']
                                          : null,
            patientName:             self::nullableString($row['patient_name'] ?? null),
            patientPid:              self::nullableString($row['patient_pid'] ?? null),
            patientGender:           self::nullableString($row['patient_gender'] ?? null),
            patientDateOfBirth:      self::toDate($row['patient_dob'] ?? null),
            visitNumber:             self::nullableString($row['visit_number'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    /**
     * A stored value that no longer parses (hand-edited, or written by a
     * future format) degrades to null rather than throwing: the order is
     * still resultable and printable without its accession number, and a
     * work queue that 500s over one malformed row is worse than one that
     * shows a dash.
     */
    private static function toAccession(mixed $value): ?AccessionNumber
    {
        return is_string($value) && $value !== '' ? AccessionNumber::tryFrom($value) : null;
    }

    public function hasResults(): bool
    {
        return $this->resultsPayloadEncrypted !== null;
    }

    public function isLab(): bool
    {
        return $this->category === DiagnosticCategory::LAB;
    }

    public function isCollected(): bool
    {
        return $this->collectedAt !== null;
    }

    /** The label to print beside the patient's name: "42 Y / Female". */
    public function patientAgeGender(?DateTimeImmutable $asOf = null): string
    {
        $parts = [];

        if ($this->patientDateOfBirth !== null) {
            $parts[] = $this->patientDateOfBirth->diff($asOf ?? new DateTimeImmutable())->y . ' Y';
        }

        $gender = $this->patientGender !== null ? Gender::tryFrom($this->patientGender) : null;

        if ($gender !== null) {
            $parts[] = $gender->label();
        }

        return $parts === [] ? '-' : implode(' / ', $parts);
    }

    /**
     * What the tube is labelled with. Falls back to the accession number,
     * which is what the requisition screen writes when the laboratory has
     * no pre-printed barcode of its own.
     */
    public function barcodeValue(): ?string
    {
        return $this->specimenBarcode ?? $this->accessionNumber?->value;
    }
}
