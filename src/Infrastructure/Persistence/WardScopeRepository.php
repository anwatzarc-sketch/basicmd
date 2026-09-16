<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Repository\WardScopeRepositoryInterface;

final class WardScopeRepository implements WardScopeRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function hasUnscopedGrant(int $userId): bool
    {
        $roleCount = $this->db->fetchInt(
            'SELECT COUNT(*) FROM user_roles WHERE user_id = :uid',
            ['uid' => $userId],
        );

        // Zero grants at all falls back to UserRole::permissions() (see
        // User::resolvePermissions()), which is not ward-restricted -
        // treat it the same as an explicit unscoped grant.
        if ($roleCount === 0) {
            return true;
        }

        $unscopedCount = $this->db->fetchInt(
            'SELECT COUNT(*) FROM user_roles ur
             WHERE ur.user_id = :uid
               AND NOT EXISTS (
                   SELECT 1 FROM user_role_scopes s
                   WHERE s.user_id = ur.user_id AND s.role_id = ur.role_id
               )',
            ['uid' => $userId],
        );

        return $unscopedCount > 0;
    }

    public function wardNamesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT ward_name FROM user_role_scopes WHERE user_id = :uid',
            ['uid' => $userId],
        );

        return array_map(static fn (array $row): string => (string) $row['ward_name'], $rows);
    }
}
