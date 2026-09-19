<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Support;

use MediCareMini\Infrastructure\Container\Bootstrap;
use MediCareMini\Infrastructure\Container\Container;
use MediCareMini\Infrastructure\Persistence\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that touch the real database.
 *
 * Boots the actual container against the real MariaDB instance named in
 * .env, then wraps the test in a transaction that is ALWAYS rolled back in
 * tearDown - so a test can freely INSERT/UPDATE/DELETE against real tables,
 * exercise real row locks and real unique-index collisions, and never leave
 * a trace.
 *
 * This is deliberate rather than a mocked PDO: the properties this codebase
 * depends on most heavily - SELECT ... FOR UPDATE serialising concurrent
 * writers, a unique index turning a race into a PDOException, SAVEPOINT
 * nesting inside Database::transaction() - are exactly the properties a
 * mock cannot verify. See bin/check-sql.php's own docblock for the same
 * argument applied to ATTR_EMULATE_PREPARES.
 *
 * IMPORTANT: code under test must not run DDL (CREATE/ALTER/DROP TABLE).
 * MariaDB/MySQL commit DDL implicitly, which would silently end the
 * surrounding transaction and desync Database's SAVEPOINT depth counter -
 * the rollback in tearDown would then do nothing. Migration-runner tests
 * use MigrationTestCase instead, which manages its own throwaway database.
 *
 * A concurrency test that needs a SECOND, independent connection (e.g. to
 * prove two processes contend for the same row lock) must not reuse the
 * container's Database - it must open its own PDO, because two connections
 * sharing one transaction cannot demonstrate contention. See
 * tests/Feature/Encounter/BedAllocationConcurrencyTest.php for the pattern
 * (a real subprocess, not a second in-process connection, is used there for
 * the same reason the existing booking-capacity test uses subprocesses).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Container $container;
    protected Database $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Bootstrap::boot(self::basePath(), cli: true);
        $this->db         = $this->container->get(Database::class);

        $this->db->begin();
    }

    protected function tearDown(): void
    {
        // Roll back even if the test already left the transaction depth at
        // zero via an internal Database::transaction() call - begin() was
        // called exactly once here, so exactly one rollback() undoes it.
        $this->db->rollback();

        parent::tearDown();
    }

    public static function basePath(): string
    {
        return dirname(__DIR__, 2);
    }
}
