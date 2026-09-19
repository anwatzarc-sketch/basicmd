<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\Role;

/**
 * Role Management persistence (spec §4.2).
 *
 * Every role beyond the seeded `super_admin` is created here, through the
 * admin screen - never hardcoded into a migration (see migration 002's own
 * comment, and the implementation report on why this codebase already had
 * six such hardcoded roles before this feature existed). Archiving, never
 * hard-deleting, is enforced at the SQL level here: delete() refuses when
 * any user_roles row still references the role.
 */
final class RoleRepository
{
    private const string SELECT_BASE = "
        SELECT r.id, r.slug, r.label, r.created_at, r.archived_at,
               GROUP_CONCAT(DISTINCT p.slug SEPARATOR ',') AS permissions,
               COUNT(DISTINCT ur.user_id) AS user_count
        FROM roles r
        LEFT JOIN role_permissions rp ON rp.role_id = r.id
        LEFT JOIN permissions p ON p.id = rp.permission_id
        LEFT JOIN user_roles ur ON ur.role_id = r.id
    ";

    private const string GROUP_BY = ' GROUP BY r.id ';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<Role> */
    public function all(bool $includeArchived = true): array
    {
        $where = $includeArchived ? '' : ' WHERE r.archived_at IS NULL ';

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where . self::GROUP_BY . ' ORDER BY r.archived_at IS NOT NULL, r.label ASC',
        );

        return array_map(Role::fromRow(...), $rows);
    }

    public function find(int $id): ?Role
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE r.id = :id' . self::GROUP_BY, ['id' => $id]);

        return $row === null ? null : Role::fromRow($row);
    }

    public function findBySlug(string $slug): ?Role
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE r.slug = :slug' . self::GROUP_BY, ['slug' => $slug]);

        return $row === null ? null : Role::fromRow($row);
    }

    public function slugExists(string $slug): bool
    {
        return $this->db->fetchInt('SELECT COUNT(*) FROM roles WHERE slug = :slug', ['slug' => $slug]) > 0;
    }

    /**
     * @param list<int> $permissionIds
     */
    public function create(string $slug, string $label, array $permissionIds): int
    {
        return $this->db->transaction(function (Database $db) use ($slug, $label, $permissionIds): int {
            $db->execute(
                'INSERT INTO roles (slug, label) VALUES (:slug, :label)',
                ['slug' => $slug, 'label' => $label],
            );

            $roleId = $db->lastInsertId();

            $this->syncPermissions($db, $roleId, $permissionIds);

            return $roleId;
        });
    }

    /**
     * @param list<int> $permissionIds
     */
    public function updatePermissions(int $roleId, array $permissionIds): void
    {
        $this->db->transaction(function (Database $db) use ($roleId, $permissionIds): void {
            $this->syncPermissions($db, $roleId, $permissionIds);
        });
    }

    public function rename(int $roleId, string $label): bool
    {
        return $this->db->execute(
            'UPDATE roles SET label = :label WHERE id = :id',
            ['label' => $label, 'id' => $roleId],
        ) > 0;
    }

    /**
     * Archive rather than hard-delete (spec §4.2) - refuses when any
     * user_roles row still points at this role, so a role in active use
     * can never be pulled out from under the users holding it.
     *
     * @return bool true on success, false when the role is still assigned
     */
    public function archive(int $roleId): bool
    {
        $assigned = $this->db->fetchInt(
            'SELECT COUNT(*) FROM user_roles WHERE role_id = :id',
            ['id' => $roleId],
        );

        if ($assigned > 0) {
            return false;
        }

        return $this->db->execute(
            'UPDATE roles SET archived_at = UTC_TIMESTAMP() WHERE id = :id AND archived_at IS NULL',
            ['id' => $roleId],
        ) > 0;
    }

    public function unarchive(int $roleId): bool
    {
        return $this->db->execute(
            'UPDATE roles SET archived_at = NULL WHERE id = :id',
            ['id' => $roleId],
        ) > 0;
    }

    /** @return list<array{id:int, slug:string}> every row in the permission catalogue */
    public function allPermissions(): array
    {
        return $this->db->fetchAll('SELECT id, slug FROM permissions ORDER BY slug ASC');
    }

    /** @param list<int> $permissionIds */
    private function syncPermissions(Database $db, int $roleId, array $permissionIds): void
    {
        $db->execute('DELETE FROM role_permissions WHERE role_id = :id', ['id' => $roleId]);

        foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
            $db->execute(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)',
                ['rid' => $roleId, 'pid' => $permissionId],
            );
        }
    }

    // -----------------------------------------------------------------
    //  Assign role to user (spec §4.2), optionally ward-scoped (§4.5).
    // -----------------------------------------------------------------

    /** @param list<string> $wardNames empty = unscoped, sees every ward for this grant */
    public function assignToUser(int $userId, int $roleId, array $wardNames = []): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $roleId, $wardNames): void {
            $db->execute(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)
                 ON DUPLICATE KEY UPDATE user_id = user_id',
                ['uid' => $userId, 'rid' => $roleId],
            );

            $db->execute(
                'DELETE FROM user_role_scopes WHERE user_id = :uid AND role_id = :rid',
                ['uid' => $userId, 'rid' => $roleId],
            );

            foreach (array_unique($wardNames) as $wardName) {
                $db->execute(
                    'INSERT INTO user_role_scopes (user_id, role_id, ward_name) VALUES (:uid, :rid, :ward)',
                    ['uid' => $userId, 'rid' => $roleId, 'ward' => $wardName],
                );
            }
        });
    }

    public function revokeFromUser(int $userId, int $roleId): void
    {
        // ON DELETE CASCADE on user_role_scopes' FK takes its scope rows
        // with it - this IS the specific grant being edited, never every
        // role a user holds (see UserRepository::syncPrimaryRole()'s own
        // docblock for the bug this mirrors and must not repeat).
        $this->db->execute(
            'DELETE FROM user_roles WHERE user_id = :uid AND role_id = :rid',
            ['uid' => $userId, 'rid' => $roleId],
        );
    }

    /**
     * Every role grant a user holds, each with its own ward scope (empty
     * = unscoped), for the assign-to-user screen and any audit view.
     *
     * @return list<array{role_id:int, slug:string, label:string, ward_names:list<string>}>
     */
    public function grantsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT ur.role_id, r.slug, r.label,
                    GROUP_CONCAT(urs.ward_name ORDER BY urs.ward_name SEPARATOR \',\') AS ward_names
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             LEFT JOIN user_role_scopes urs ON urs.user_id = ur.user_id AND urs.role_id = ur.role_id
             WHERE ur.user_id = :uid
             GROUP BY ur.role_id, r.slug, r.label
             ORDER BY r.label ASC',
            ['uid' => $userId],
        );

        return array_map(static function (array $row): array {
            $wardNames = $row['ward_names'] ?? null;

            return [
                'role_id'    => (int) $row['role_id'],
                'slug'       => (string) $row['slug'],
                'label'      => (string) $row['label'],
                'ward_names' => is_string($wardNames) && $wardNames !== ''
                    ? explode(',', $wardNames)
                    : [],
            ];
        }, $rows);
    }
}
