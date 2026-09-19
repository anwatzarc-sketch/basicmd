<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\ValueObject\Money;

/** A bed, room, or transient (consultation/waiting) location (FRS 5.4). */
final readonly class WardLocation
{
    public function __construct(
        public int $id,
        public string $wardName,
        public string $roomNumber,
        public string $bedNumber,
        public bool $isTransient,
        public bool $isOccupied,
        public Money $dailyRate,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            wardName:     (string) $row['ward_name'],
            roomNumber:   (string) $row['room_number'],
            bedNumber:    (string) $row['bed_number'],
            isTransient:  (bool) ($row['is_transient'] ?? false),
            isOccupied:   (bool) ($row['is_occupied'] ?? false),
            dailyRate:    Money::fromDatabase($row['daily_rate'] ?? 0),
        );
    }

    /** "ICU Ward - Room 3 - Bed B" */
    public function displayLabel(): string
    {
        return sprintf('%s - Room %s - Bed %s', $this->wardName, $this->roomNumber, $this->bedNumber);
    }

    /**
     * Whether this location can receive a new IPD admission right now.
     *
     * A transient location (consultation room, waiting bay) is never an
     * admission target - FRS 8.2's precondition "The target location must
     * be an admission location according to the supplied model" is this.
     */
    public function isAvailableForAdmission(): bool
    {
        return !$this->isTransient && !$this->isOccupied;
    }
}
