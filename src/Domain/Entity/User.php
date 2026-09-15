<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\Locale;
use Aster\Domain\Enum\UserRole;
use Aster\Domain\Enum\UserStatus;
use DateTimeImmutable;

/**
 * A staff account.
 *
 * Readonly: an entity is a snapshot of a row, never a live handle onto it.
 * Changes go through a repository method that writes explicit columns, which
 * keeps every mutation visible in one place and auditable.
 *
 * The password hash is deliberately NOT a property. It is loaded only by the
 * authentication path, so it cannot leak into a view, a JSON response or a
 * var_dump of an entity.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $email,
        public ?string $phone,
        public UserRole $role,
        public UserStatus $status,
        public Locale $locale,
        public int $failedAttempts,
        public ?DateTimeImmutable $lockedUntil,
        public ?DateTimeImmutable $lastLoginAt,
        public bool $mustChangePassword,
        public DateTimeImmutable $createdAt,
        /** Set when this user is also a clinician, for own-queue scoping. */
        public ?int $doctorId = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                 (int) $row['id'],
            fullName:           (string) $row['full_name'],
            email:              (string) $row['email'],
            phone:              $row['phone'] !== null ? (string) $row['phone'] : null,
            role:               UserRole::from((string) $row['role']),
            status:             UserStatus::from((string) $row['status']),
            locale:             Locale::from((string) ($row['locale'] ?? 'en')),
            failedAttempts:     (int) ($row['failed_attempts'] ?? 0),
            lockedUntil:        self::toDate($row['locked_until'] ?? null),
            lastLoginAt:        self::toDate($row['last_login_at'] ?? null),
            mustChangePassword: (bool) ($row['must_change_password'] ?? false),
            createdAt:          self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            doctorId:           isset($row['doctor_id']) ? (int) $row['doctor_id'] : null,
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // Stored UTC; callers localise for display.
        return new DateTimeImmutable($value . ' UTC');
    }

    public function can(string $permission): bool
    {
        return $this->role->can($permission);
    }

    /** True when the account is locked out right now. */
    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new DateTimeImmutable();
    }

    public function canAuthenticate(): bool
    {
        return $this->status->canLogin() && !$this->isLocked();
    }

    /**
     * Whether this user only sees their own appointments.
     * A doctor without a linked doctors row would otherwise see everything,
     * so the absence of the link is treated as the restrictive case.
     */
    public function isScopedToOwnQueue(): bool
    {
        return $this->role->isClinical();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->fullName)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            // Skip honorifics so "Dr. Hana Tesfaye" gives HT, not DH.
            if (in_array(rtrim(mb_strtolower($part), '.'), ['dr', 'mr', 'mrs', 'ms', 'prof'], true)) {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        if ($letters === '') {
            $letters = mb_strtoupper(mb_substr($this->fullName, 0, 2));
        }

        return mb_substr($letters, 0, 2);
    }

    /** First name only, for a friendly greeting in the admin header. */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim($this->fullName)) ?: [$this->fullName];

        if (count($parts) > 1 && in_array(rtrim(mb_strtolower($parts[0]), '.'), ['dr', 'mr', 'mrs', 'ms', 'prof'], true)) {
            return $parts[0] . ' ' . $parts[1];
        }

        return $parts[0];
    }
}
