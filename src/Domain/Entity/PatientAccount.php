<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use DateTimeImmutable;

/**
 * A patient's own portal credential (FRS 5.3) - deliberately separate from
 * Patient itself, the same way `users` is separate from staff identity: the
 * clinical record and the login credential are different concerns with
 * different lifecycles (a patient exists in the MPI long before, and
 * regardless of whether, they ever get portal access).
 */
final readonly class PatientAccount
{
    public function __construct(
        public int $id,
        public int $patientId,
        public string $passwordHash,
        public bool $isActive,
        public ?DateTimeImmutable $lastLoginAt,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            patientId:    (int) $row['patient_id'],
            passwordHash: (string) $row['password_hash'],
            isActive:     (bool) $row['is_active'],
            lastLoginAt:  self::toDate($row['last_login_at'] ?? null),
            createdAt:    self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    public function canAuthenticate(): bool
    {
        return $this->isActive;
    }
}
