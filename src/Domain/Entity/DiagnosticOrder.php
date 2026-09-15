<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\DiagnosticCategory;
use Aster\Domain\Enum\DiagnosticStatus;
use DateTimeImmutable;

/**
 * A lab/imaging/PACS request tied to an encounter (FRS 6.2).
 *
 * resultsPayloadEncrypted carries CIPHERTEXT - see ClinicalNote's docblock
 * for the same boundary and why.
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
            resultsPayloadEncrypted: isset($row['results_payload_encrypted']) && $row['results_payload_encrypted'] !== null
                                          ? (string) $row['results_payload_encrypted']
                                          : null,
            createdAt:               self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            updatedAt:               self::toDate($row['updated_at'] ?? null) ?? new DateTimeImmutable(),
            physicianName:           isset($row['physician_name']) ? (string) $row['physician_name'] : null,
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    public function hasResults(): bool
    {
        return $this->resultsPayloadEncrypted !== null;
    }
}
