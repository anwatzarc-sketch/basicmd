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
    /** Doctor link is joined in so a clinician's own-queue scope is one query. */
    private const string SELECT_BASE = '
        SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.locale,
               u.failed_attempts, u.locked_until, u.last_login_at,
               u.must_change_password, u.created_at,
               d.id AS doctor_id
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id AND d.deleted_at IS NULL
    ';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE u.id = :id AND u.deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE u.email = :email AND u.deleted_at IS NULL',
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
            self::SELECT_BASE . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY u.role ASC, u.full_name ASC',
            $params,
        );

        return array_map(User::fromRow(...), $rows);
    }

    public function create(
        string $fullName,
        string $email,
        string $passwordHash,
        UserRole $role,
        ?string $phone = null,
        string $status = 'active',
        bool $mustChangePassword = false,
    ): int {
        $this->db->execute(
            'INSERT INTO users (full_name, email, phone, password_hash, role, status, must_change_password)
             VALUES (:name, :email, :phone, :hash, :role, :status, :must)',
            [
                'name'   => $fullName,
                'email'  => mb_strtolower(trim($email)),
                'phone'  => $phone,
                'hash'   => $passwordHash,
                'role'   => $role->value,
                'status' => $status,
                'must'   => $mustChangePassword ? 1 : 0,
            ],
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        return $this->db->execute(
            "UPDATE users SET {$assignments} WHERE id = :id AND deleted_at IS NULL",
            $data,
        ) > 0;
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
