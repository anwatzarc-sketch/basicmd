<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use DateTimeImmutable;

/**
 * A platform-created role (spec §4.2). Unlike UserRole, this is not an
 * enum - `roles` is a lookup table a super_admin populates through the
 * Role Management screen, and this entity is a snapshot of one such row.
 *
 * archivedAt marks a role retired from new assignment (never hard-deleted
 * - a `user_roles` row must never be able to outlive the role it points
 * at, and archiving is how that stays true without an orphan).
 */
final readonly class Role
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $label,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $archivedAt = null,
        /** @var list<string> permission slugs currently held by this role */
        public array $permissions = [],
        public int $userCount = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $permissions = $row['permissions'] ?? null;

        return new self(
            id:         (int) $row['id'],
            slug:       (string) $row['slug'],
            label:      (string) $row['label'],
            createdAt:  new DateTimeImmutable((string) $row['created_at'] . ' UTC'),
            archivedAt: isset($row['archived_at']) && $row['archived_at'] !== null
                ? new DateTimeImmutable((string) $row['archived_at'] . ' UTC')
                : null,
            permissions: is_string($permissions) && $permissions !== ''
                ? array_values(array_unique(array_filter(explode(',', $permissions))))
                : [],
            userCount: (int) ($row['user_count'] ?? 0),
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
