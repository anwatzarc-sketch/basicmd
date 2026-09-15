<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Repository\StaffDirectoryInterface;

final readonly class StaffDirectory implements StaffDirectoryInterface
{
    public function __construct(private Database $db)
    {
    }

    public function isActivePhysician(int $userId): bool
    {
        $count = $this->db->fetchInt(
            "SELECT COUNT(*) FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :id AND u.status = 'active' AND u.deleted_at IS NULL
               AND r.slug = 'physician'",
            ['id' => $userId],
        );

        return $count > 0;
    }

    public function isActiveStaffUser(int $userId): bool
    {
        return $this->db->fetchInt(
            "SELECT COUNT(*) FROM users WHERE id = :id AND status = 'active' AND deleted_at IS NULL",
            ['id' => $userId],
        ) > 0;
    }
}
