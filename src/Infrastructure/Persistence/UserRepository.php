<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\User;
use Aster\Domain\Enum\UserRole;

/**
 * Staff account persistence.
 *
 * The password hash is never returned as part of a User entity. It is loaded
 * only by findCredentials(), which the authentication service alone calls, so
 * a hash cannot end up in a template or a JSON response by accident.
 */
final class UserRepository
{
    /**
     * Doctor link is joined in so a clinician's own-queue scope is one query.
     *
     * resolved_permissions is the user's DB-backed permission set (via
     * user_roles -> role_permissions -> permissions), collapsed to one
     * comma-separated column with GROUP_CONCAT so User::fromRow() gets it
     * in the same single-row query rather than a second round trip - this
     * runs on every authenticated request (AuthService::currentUser()), so
     * an N+1 here would mean an N+1 on every page. GROUP BY u.id is safe
     * without ONLY_FULL_GROUP_BY (which this connection's sql_mode does not
     * set - see Database::pdo()) because every other selected column is
     * functionally dependent on u.id, the primary key.
     *
     * role_count (COUNT(DISTINCT ur.role_id)) exists solely so
     * User::resolvePermissions() can tell "this user holds zero user_roles
     * rows at all" (0 - the hardcoded UserRole::permissions() matrix is the
     * correct fallback) apart from "this user holds a real role that
     * resolves to zero permissions" (>= 1 with a NULL resolved_permissions -
     * must be honoured as empty, never broadened). GROUP_CONCAT alone
     * cannot distinguish those two cases.
     *
     * role_label resolves the created role's own `roles.label` for a
     * users.role value the fixed UserRole enum does not recognise, so the
     * display-only label (spec §4.3) still reads correctly for a role
     * Role Management created.
     */
    private const string SELECT_BASE = '
        SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.locale,
               u.failed_attempts, u.locked_until, u.last_login_at,
               u.must_change_password, u.created_at,
               d.id AS doctor_id,
               rl.label AS role_label,
               COUNT(DISTINCT ur.role_id) AS role_count,
               GROUP_CONCAT(DISTINCT p.slug SEPARATOR \',\') AS resolved_permissions
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id AND d.deleted_at IS NULL
        LEFT JOIN roles rl ON rl.slug = u.role
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
        LEFT JOIN permissions p ON p.id = rp.permission_id
    ';

    private const string GROUP_BY = ' GROUP BY u.id, d.id ';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE u.id = :id AND u.deleted_at IS NULL' . self::GROUP_BY,
            ['id' => $id],
        );

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE u.email = :email AND u.deleted_at IS NULL' . self::GROUP_BY,
            ['email' => mb_strtolower(trim($email))],
        );

        return $row === null ? null : User::fromRow($row);
    }

    /**
     * Load the row including the password hash, for authentication only.
     *
     * @return array<string, mixed>|null
     */
    public function findCredentials(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, email, password_hash, role, status, failed_attempts, locked_until, must_change_password
             FROM users
             WHERE email = :email AND deleted_at IS NULL',
            ['email' => mb_strtolower(trim($email))],
        );
    }

    /** @return array<string, mixed>|null */
    public function findCredentialsById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, email, password_hash FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );
    }

    /** @return list<User> */
    public function all(?string $role = null, ?string $status = null): array
    {
        $where  = ['u.deleted_at IS NULL'];
        $params = [];

        if ($role !== null && $role !== '') {
            $where[]        = 'u.role = :role';
            $params['role'] = $role;
        }

        if ($status !== null && $status !== '') {
            $where[]          = 'u.status = :status';
            $params['status'] = $status;
        }

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where) . self::GROUP_BY
            . ' ORDER BY u.role ASC, u.full_name ASC',
            $params,
        );

        return array_map(User::fromRow(...), $rows);
    }

    /**
     * $role accepts a plain slug string so ANY role Role Management has
     * created can be assigned as a user's primary role, not just the six
     * fixed UserRole cases - or a UserRole instance, kept for every
     * existing caller (bin/install.php, tests) that already passes one.
     */
    public function create(
        string $fullName,
        string $email,
        string $passwordHash,
        UserRole|string $role,
        ?string $phone = null,
        string $status = 'active',
        bool $mustChangePassword = false,
    ): int {
        $roleSlug = $role instanceof UserRole ? $role->value : $role;

        return $this->db->transaction(function (Database $db) use (
            $fullName, $email, $passwordHash, $roleSlug, $phone, $status, $mustChangePassword,
        ): int {
            $db->execute(
                'INSERT INTO users (full_name, email, phone, password_hash, role, status, must_change_password)
                 VALUES (:name, :email, :phone, :hash, :role, :status, :must)',
                [
                    'name'   => $fullName,
                    'email'  => mb_strtolower(trim($email)),
                    'phone'  => $phone,
                    'hash'   => $passwordHash,
                    'role'   => $roleSlug,
                    'status' => $status,
                    'must'   => $mustChangePassword ? 1 : 0,
                ],
            );

            $userId = $db->lastInsertId();

            $this->syncPrimaryRole($db, $userId, $roleSlug);

            return $userId;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        // users.role is a synced cache of the relational assignment (see
        // migration 002_rbac.sql's comment on why the column is retained).
        // Writing it here without also touching user_roles would let the
        // two drift, and User::fromRow()'s permission resolution reads
        // user_roles - a drifted row would keep the OLD permission set
        // after an admin visibly changed someone's role.
        //
        // Any non-empty string is a candidate slug to sync - NOT gated to
        // UserRole::tryFrom() any more. That gate used to silently skip
        // syncPrimaryRole() whenever the submitted role was one Role
        // Management created (since the fixed enum has never heard of
        // it), leaving user_roles pointing at the OLD role while
        // users.role visibly showed the new one. syncPrimaryRole() itself
        // already resolves the slug against the real `roles` table, so it
        // safely no-ops on a bogus slug without this pre-filter.
        $newRoleSlug = isset($data['role']) && is_string($data['role']) && $data['role'] !== ''
            ? $data['role']
            : null;

        return $this->db->transaction(function (Database $db) use ($id, $data, $newRoleSlug): bool {
            // Captured BEFORE the UPDATE below overwrites it - this is the
            // "previous primary role" syncPrimaryRole() needs to know
            // exactly which user_roles row to retire, rather than wiping
            // every row this user holds (see that method's docblock).
            $previousRoleSlug = $newRoleSlug !== null
                ? $db->fetchValue('SELECT role FROM users WHERE id = :id', ['id' => $id])
                : null;

            $assignments = implode(', ', array_map(
                static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
                array_keys($data),
            ));

            $data['id'] = $id;

            $changed = $db->execute(
                "UPDATE users SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
                $data,
            ) > 0;

            if ($changed && $newRoleSlug !== null) {
                $this->syncPrimaryRole($db, $id, $newRoleSlug, is_string($previousRoleSlug) ? $previousRoleSlug : null);
            }

            return $changed;
        });
    }

    /**
     * Keep users.role's "primary" role assignment in agreement with
     * user_roles, touching ONLY that one grant.
     *
     * Deliberately NOT a delete-all-then-reinsert: a user can hold
     * additional roles assigned through Role Management (§4.2/§4.5),
     * each possibly carrying its own user_role_scopes rows. Every save of
     * this form always resubmits the 'role' select (see
     * UserController::save()), so a delete-all here would silently wipe
     * every one of those extra grants - and their ward scopes with them,
     * via the ON DELETE CASCADE on user_role_scopes - on every ordinary
     * profile edit, not just an actual role change. Removing only the
     * role_id that matches the OLD primary slug (and no-op'ing the insert
     * via ON DUPLICATE KEY when the role did not actually change) fixes
     * that while still keeping create()'s single-grant case unaffected
     * ($previousRoleSlug is null there, so nothing is deleted).
     *
     * $roleSlug is a plain string, not a UserRole - this must resolve ANY
     * role Role Management has created, not just the six fixed cases.
     */
    private function syncPrimaryRole(Database $db, int $userId, string $roleSlug, ?string $previousRoleSlug = null): void
    {
        $roleId = $db->fetchValue('SELECT id FROM roles WHERE slug = :slug', ['slug' => $roleSlug]);

        if ($roleId === null) {
            // Either the relational catalogue has not been seeded yet (e.g.
            // migration 002 has not run in this environment), or the
            // caller passed a slug that does not exist in `roles` at all.
            // users.role above is still written either way, and
            // User::resolvePermissions() falls back to the hardcoded
            // matrix whenever no relational rows exist, so this is a
            // silent no-op rather than a failure.
            return;
        }

        if ($previousRoleSlug !== null && $previousRoleSlug !== $roleSlug) {
            $db->execute(
                'DELETE FROM user_roles
                 WHERE user_id = :uid AND role_id = (SELECT id FROM roles WHERE slug = :slug)',
                ['uid' => $userId, 'slug' => $previousRoleSlug],
            );
        }

        $db->execute(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)
             ON DUPLICATE KEY UPDATE user_id = user_id',
            ['uid' => $userId, 'rid' => $roleId],
        );
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        return $this->db->execute(
            'UPDATE users
             SET password_hash = :hash, must_change_password = 0, failed_attempts = 0, locked_until = NULL
             WHERE id = :id',
            ['hash' => $passwordHash, 'id' => $id],
        ) > 0;
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => mb_strtolower(trim($email))];

        if ($ignoreId !== null) {
            $sql         .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return $this->db->fetchInt($sql, $params) > 0;
    }

    // --- Login throttling ----------------------------------------------

    /**
     * Record a failed attempt and lock the account once the threshold is hit.
     *
     * Per-account, on top of the per-IP rate limit. An attacker rotating
     * through a proxy pool defeats IP limits but still cannot brute-force one
     * account past this ceiling.
     *
     * @return bool true when this failure triggered a lockout
     */
    public function registerFailedLogin(int $userId, int $maxAttempts, int $lockoutMinutes): bool
    {
        $this->db->execute(
            'UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id',
            ['id' => $userId],
        );

        $attempts = $this->db->fetchInt(
            'SELECT failed_attempts FROM users WHERE id = :id',
            ['id' => $userId],
        );

        if ($attempts < $maxAttempts) {
            return false;
        }

        $this->db->execute(
            'UPDATE users
             SET locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :mins MINUTE), failed_attempts = 0
             WHERE id = :id',
            ['mins' => $lockoutMinutes, 'id' => $userId],
        );

        return true;
    }

    public function registerSuccessfulLogin(int $userId, ?string $ipBinary): void
    {
        $this->db->execute(
            'UPDATE users
             SET failed_attempts = 0, locked_until = NULL,
                 last_login_at = UTC_TIMESTAMP(), last_login_ip = :ip
             WHERE id = :id',
            ['ip' => $ipBinary, 'id' => $userId],
        );
    }

    public function unlock(int $userId): bool
    {
        return $this->db->execute(
            'UPDATE users SET locked_until = NULL, failed_attempts = 0 WHERE id = :id',
            ['id' => $userId],
        ) > 0;
    }

    public function softDelete(int $id): bool
    {
        // The email is released on delete so the address can be re-registered
        // later, while the row itself survives for the audit trail.
        return $this->db->execute(
            "UPDATE users
             SET deleted_at = UTC_TIMESTAMP(),
                 status = 'suspended',
                 email = CONCAT('deleted+', id, '@invalid.local')
             WHERE id = :id",
            ['id' => $id],
        ) > 0;
    }

    public function countByRole(): array
    {
        return array_map('intval', $this->db->fetchPairs(
            "SELECT role, COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'active' GROUP BY role"
        ));
    }

    /** @return array<string,mixed>|null */
    public function rawRow(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, full_name, email, phone, role, status, locale FROM users WHERE id = :id',
            ['id' => $id],
        );
    }

    /** True when no staff account exists yet, used by the installer. */
    public function isEmpty(): bool
    {
        return $this->db->fetchInt('SELECT COUNT(*) FROM users') === 0;
    }
}
