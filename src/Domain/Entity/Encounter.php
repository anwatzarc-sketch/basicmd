<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\Enum\FinancialClearanceStatus;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\ValueObject\VisitNumber;
use DateTimeImmutable;

/**
 * A single continuous clinical/operational interaction (FRS 5.5).
 *
 * patientVisitNumber never changes across an OPD-to-IPD conversion
 * (ENC-001) - EncounterService::upgradeOpdToIpd() updates visitType,
 * status, currentLocationId and admittedAt on the SAME row; it never
 * issues a new VisitNumber or creates a second encounter.
 */
final readonly class Encounter
{
    public function __construct(
        public int $id,
        public VisitNumber $patientVisitNumber,
        public int $patientId,
        public ?int $appointmentId,
        public ?int $primaryPhysicianId,
        public ?int $currentLocationId,
        public VisitType $visitType,
        public EncounterStatus $status,
        public FinancialClearanceStatus $financialClearanceStatus,
        public ?DateTimeImmutable $admittedAt,
        public ?DateTimeImmutable $dischargedAt,
        public ?int $createdBy,
        public DateTimeImmutable $createdAt,
        // Denormalised display labels from a JOIN, read-only, never
        // written back - the same pattern Appointment uses for
        // doctor_name/service_name.
        public ?string $patientName = null,
        public ?string $physicianName = null,
        public ?string $locationLabel = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                        (int) $row['id'],
            patientVisitNumber:        VisitNumber::fromString((string) $row['patient_visit_number']),
            patientId:                 (int) $row['patient_id'],
            appointmentId:              self::nullableInt($row['appointment_id'] ?? null),
            primaryPhysicianId:         self::nullableInt($row['primary_physician_id'] ?? null),
            currentLocationId:          self::nullableInt($row['current_location_id'] ?? null),
            visitType:                  VisitType::from((string) $row['visit_type']),
            status:                     EncounterStatus::from((string) $row['status']),
            financialClearanceStatus:  FinancialClearanceStatus::from((string) ($row['financial_clearance_status'] ?? 'PENDING')),
            admittedAt:                 self::toDate($row['admitted_at'] ?? null),
            dischargedAt:               self::toDate($row['discharged_at'] ?? null),
            createdBy:                   self::nullableInt($row['created_by'] ?? null),
            createdAt:                   self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            patientName:                 isset($row['patient_name']) ? (string) $row['patient_name'] : null,
            physicianName:               isset($row['physician_name']) ? (string) $row['physician_name'] : null,
            locationLabel:               isset($row['location_label']) ? (string) $row['location_label'] : null,
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    public function isActive(): bool
    {
        return !$this->status->isTerminal();
    }

    public function isAdmitted(): bool
    {
        return $this->status === EncounterStatus::ADMITTED;
    }

    public function isFinanciallyClear(): bool
    {
        return $this->financialClearanceStatus === FinancialClearanceStatus::APPROVED
            || $this->financialClearanceStatus === FinancialClearanceStatus::OVERRIDDEN;
    }
}
