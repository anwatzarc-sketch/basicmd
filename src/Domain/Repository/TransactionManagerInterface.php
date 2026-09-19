<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

/**
 * Transaction boundary port. A Domain service orchestrates a multi-step
 * write (e.g. EncounterService::upgradeOpdToIpd() locking a bed AND
 * updating an encounter) without depending on PDO or Database directly -
 * "ports in Domain, PDO in Infrastructure" per the approved plan. The
 * Infrastructure implementation is a thin wrapper over the existing
 * Database::transaction(), which already handles SAVEPOINT nesting.
 */
interface TransactionManagerInterface
{
    /**
     * Run $callback inside a database transaction. On any Throwable, the
     * transaction is rolled back and the exception re-thrown; otherwise it
     * is committed and the callback's return value passed through.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed;
}
