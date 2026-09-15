<?php

declare(strict_types=1);

namespace Aster\Tests\Support;

use Aster\Infrastructure\Persistence\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that exercise the migration runner itself.
 *
 * MigrationRunner runs real DDL, which implicitly commits on MySQL/MariaDB -
 * so it cannot be tested inside DatabaseTestCase's rollback-per-test
 * transaction (see MigrationRunner's own docblock for why). Instead each
 * test gets its own throwaway database, created fresh in setUp() and dropped
 * in tearDown(), using the same connection credentials as the real app but a
 * disposable schema name so a failed test run cannot collide with (or ever
 * touch) aster_medical.
 */
abstract class MigrationTestCase extends TestCase
{
    protected PDO $pdo;
    protected Database $db;
    protected string $testDatabaseName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDatabaseName = 'aster_migration_test_' . bin2hex(random_bytes(4));

        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->pdo->exec(
            'CREATE DATABASE `' . $this->testDatabaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        );

        // Mirrors Database::fromEnv() exactly - in particular FETCH_ASSOC,
        // without which PDO defaults to FETCH_BOTH and every row comes back
        // with duplicate numeric keys alongside the column names, which
        // silently corrupts fetchPairs()'s array_values()-based pairing.
        $this->db = new Database(
            'mysql:host=127.0.0.1;dbname=' . $this->testDatabaseName . ';charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES    => false,
                PDO::ATTR_STRINGIFY_FETCHES   => false,
            ],
        );
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->testDatabaseName . '`');

        parent::tearDown();
    }

    /** Write a temporary migration file under a fresh temp directory and return its dir. */
    protected function writeMigrationDir(array $filesByName): string
    {
        $dir = sys_get_temp_dir() . '/aster_migrations_test_' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);

        foreach ($filesByName as $filename => $contents) {
            file_put_contents($dir . '/' . $filename, $contents);
        }

        return $dir;
    }
}
