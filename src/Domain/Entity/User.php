<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\Enum\UserRole;
use MediCareMini\Domain\Enum\UserStatus;
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
        /**
         * Null when users.role holds a value the fixed UserRole enum does
         * not recognise - e.g. a user whose only assignment is a role
         * created through Role Management. Never read for an
         * authorization decision (spec §4.3); every genuine check goes
         * through can(). See $roleLabel for display.
         */
        public ?UserRole $role,
        /**
         * The raw users.role slug, unconditionally - present even when it
         * does not map to a known UserRole case (a role Role Management
         * created). $role above is a typed convenience for the fixed
         * legacy cases; this is the actual value, needed anywhere code
         * must compare or re-submit "this user's current primary role"
         * without losing a custom slug down to null (e.g. the
         * self-lockout guard in UserController::save()).
         */
        public ?string $roleSlug,
        /**
         * The raw, always-populated display label - users.role's actual
         * value when it maps to a known UserRole, otherwise the created
         * role's own label from the `roles` table (falling back to the
         * raw slug if even that lookup came back empty). This is the
         * "non-authoritative display label" spec §4.3 describes; nothing
         * in this class or its callers uses it for an access decision.
         */
        public string $roleLabel,
        public UserStatus $status,
        public Locale $locale,
        public int $failedAttempts,
        public ?DateTimeImmutable $lockedUntil,
        public ?DateTimeImmutable $lastLoginAt,
        public bool $mustChangePassword,
        public DateTimeImmutable $createdAt,
        /** Set when this user is also a clinician, for own-queue scoping. */
        public ?int $doctorId = null,
        /**
         * The permission set actually held, resolved once at hydration time
         * rather than on every can() call. See the doc comment on can().
         *
         * @var list<string>
         */
        public array $permissions = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        // Tolerant, not UserRole::from(): users.role can hold a role name
        // Role Management created that this fixed enum has never heard
        // of (spec §4.3) - that must hydrate a User, not throw a
        // ValueError on every authenticated request.
        $role = UserRole::tryFrom((string) ($row['role'] ?? ''));

        return new self(
            id:                 (int) $row['id'],
            fullName:           (string) $row['full_name'],
            email:              (string) $row['email'],
            phone:              $row['phone'] !== null ? (string) $row['phone'] : null,
            role:               $role,
            roleSlug:           isset($row['role']) && $row['role'] !== null ? (string) $row['role'] : null,
            roleLabel:          self::resolveRoleLabel($row, $role),
            status:             UserStatus::from((string) $row['status']),
            locale:             Locale::from((string) ($row['locale'] ?? 'en')),
            failedAttempts:     (int) ($row['failed_attempts'] ?? 0),
            lockedUntil:        self::toDate($row['locked_until'] ?? null),
            lastLoginAt:        self::toDate($row['last_login_at'] ?? null),
            mustChangePassword: (bool) ($row['must_change_password'] ?? false),
            createdAt:          self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            doctorId:           isset($row['doctor_id']) ? (int) $row['doctor_id'] : null,
            permissions:        self::resolvePermissions($row, $role),
        );
    }

    /**
     * The DB-resolved permission set (roles -> role_permissions), falling
     * back to the hardcoded UserRole::permissions() matrix ONLY when this
     * row never resolved through user_roles at all - a user with zero
     * user_roles rows (role_count 0), or a query that never joined
     * user_roles in the first place (role_count absent entirely, e.g.
     * UserRepository::rawRow()'s partial select). That keeps a User
     * usable everywhere it is constructed from such a row, and means the
     * relational model can only ever GRANT what the hardcoded matrix
     * already granted for a legacy role with none of its own rows yet,
     * never silently revoke it through an incomplete join.
     *
     * Critically, this must NOT fire for a user who genuinely holds a
     * role (role_count >= 1) that simply resolves to zero permissions -
     * e.g. a brand-new role created through Role Management before its
     * checklist has been ticked. That case is honoured as a real, empty
     * permission set, never broadened into the fallback matrix.
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function resolvePermissions(array $row, ?UserRole $role): array
    {
        $roleCount = array_key_exists('role_count', $row) ? (int) $row['role_count'] : null;

        if ($roleCount === 0) {
            return $role?->permissions() ?? [];
        }

        $raw = $row['resolved_permissions'] ?? null;

        if (is_string($raw) && $raw !== '') {
            $permissions = array_filter(explode(',', $raw), static fn (string $p): bool => $p !== '');

            return array_values(array_unique($permissions));
        }

        // resolved_permissions is empty/NULL. If role_count was never
        // queried at all, we cannot tell "zero rows" from "a real role
        // with zero permissions" - fall back to the matrix, matching the
        // pre-existing behaviour for a partial row. If it WAS queried and
        // came back >= 1, this is a real, resolved-empty grant.
        return $roleCount === null ? ($role?->permissions() ?? []) : [];
    }

    /**
     * The always-populated display label behind users.role (spec §4.3) -
     * never consulted for an access decision, only shown on screen.
     *
     * @param array<string, mixed> $row
     */
    private static function resolveRoleLabel(array $row, ?UserRole $role): string
    {
        if ($role !== null) {
            return $role->label();
        }

        $seededLabel = $row['role_label'] ?? null;

        if (is_string($seededLabel) && $seededLabel !== '') {
            return $seededLabel;
        }

        $raw = $row['role'] ?? null;

        return is_string($raw) && $raw !== '' ? $raw : 'Unassigned';
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // Stored UTC; callers localise for display.
        return new DateTimeImmutable($value . ' UTC');
    }

    /**
     * Whether this user holds the given permission.
     *
     * Checks the resolved set computed once in fromRow() - a plain in-memory
     * lookup, not a database call, which matters because this is invoked up
     * to ~15 times rendering a single admin page (the sidebar nav loop plus
     * every per-section gate).
     */
    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
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
     *
     * Resolved from the permission set, never from users.role or a role
     * name (spec §4.3) - appointments.view_all is the exact permission
     * that already distinguishes "sees every clinician's queue" from
     * "sees only their own" (receptionist/accountant/super_admin hold it,
     * physician never has), so a role created through Role Management
     * that grants appointments.view without view_all gets the same
     * own-queue restriction with zero code change.
     *
     * A doctor without a linked doctors row would otherwise see
     * everything, so the absence of the link is treated as the
     * restrictive case (see call sites: doctorId ?? -1).
     */
    public function isScopedToOwnQueue(): bool
    {
        return !$this->can('appointments.view_all');
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
