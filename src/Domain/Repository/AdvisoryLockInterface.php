<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use RuntimeException;

/**
 * A named, database-backed mutual-exclusion lock, for serialising a
 * check-then-write sequence that no unique index can protect.
 *
 * PatientDeduplicationService is the reason this exists (MPI-007: "Handle
 * concurrent registration safely"). national_id has a unique DB
 * constraint, so a race there is caught for free by a duplicate-key
 * error. Matching on phone number or on name+date-of-birth has no such
 * constraint - and deliberately cannot: two real patients legitimately
 * share a household phone, so phone_number must never be UNIQUE. Without
 * a lock, two concurrent registrations for the same new patient could
 * both pass the "no existing match" check before either inserts, and
 * create two rows for one person. withLock() closes that window by
 * serialising registration attempts that key to the same identity
 * fingerprint (see PatientDeduplicationService), while leaving unrelated
 * registrations (different phone numbers) to proceed independently.
 */
interface AdvisoryLockInterface
{
    /**
     * Run $callback while holding an exclusive lock on $key, waiting up to
     * $timeoutSeconds to acquire it first.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws RuntimeException when the lock could not be acquired in time
     */
    public function withLock(string $key, callable $callback, int $timeoutSeconds = 5): mixed;
}
