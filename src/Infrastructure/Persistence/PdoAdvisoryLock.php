<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Repository\AdvisoryLockInterface;
use RuntimeException;

/**
 * MariaDB named locks (GET_LOCK / RELEASE_LOCK) implementing the Domain
 * AdvisoryLockInterface port.
 *
 * These locks are SESSION-scoped, not transaction-scoped: they survive a
 * COMMIT and are released only by RELEASE_LOCK(), a second GET_LOCK() on
 * the same connection releasing the first, or the connection closing.
 * withLock() always releases in a finally block, so a thrown domain
 * exception from inside $callback cannot leak the lock. Verified directly
 * against the live server: a second connection requesting the same key
 * blocks/returns 0 while the first holds it, a different key is
 * unaffected, and release makes the key immediately acquirable again.
 */
final readonly class PdoAdvisoryLock implements AdvisoryLockInterface
{
    public function __construct(private Database $db)
    {
    }

    public function withLock(string $key, callable $callback, int $timeoutSeconds = 5): mixed
    {
        // Key length is capped at 64 bytes by MariaDB itself; hashing keeps
        // this safe regardless of what the caller passes (a phone number,
        // a composite string, etc.) without silently truncating two
        // different long keys down to the same lock.
        $lockName = 'medicaremini:' . substr(hash('sha256', $key), 0, 40);

        $acquired = $this->db->fetchValue(
            'SELECT GET_LOCK(:name, :timeout)',
            ['name' => $lockName, 'timeout' => $timeoutSeconds],
        );

        if ((int) $acquired !== 1) {
            throw new RuntimeException("Could not acquire lock for '{$key}' within {$timeoutSeconds}s.");
        }

        try {
            return $callback();
        } finally {
            $this->db->execute('SELECT RELEASE_LOCK(:name)', ['name' => $lockName]);
        }
    }
}
