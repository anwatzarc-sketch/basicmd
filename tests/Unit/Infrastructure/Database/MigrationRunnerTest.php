<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Unit\Infrastructure\Database;

use MediCareMini\Infrastructure\Database\MigrationRunner;
use MediCareMini\Tests\Support\MigrationTestCase;
use RuntimeException;

final class MigrationRunnerTest extends MigrationTestCase
{
    public function test_discovers_and_sorts_by_numeric_prefix(): void
    {
        $dir = $this->writeMigrationDir([
            '003_third.sql'  => 'CREATE TABLE c (id INT);',
            '001_first.sql'  => 'CREATE TABLE a (id INT);',
            '010_tenth.sql'  => 'CREATE TABLE j (id INT);',
            '002_second.sql' => 'CREATE TABLE b (id INT);',
            'not-a-migration.txt' => 'ignored',
            'README.md'      => 'ignored',
        ]);

        $runner   = new MigrationRunner($this->db, $dir);
        $versions = array_column($runner->discover(), 'version');

        self::assertSame([1, 2, 3, 10], $versions, 'numeric sort must not be lexicographic (10 before 2)');
    }

    public function test_rejects_duplicate_version_numbers(): void
    {
        $dir = $this->writeMigrationDir([
            '001_first.sql'      => 'CREATE TABLE a (id INT);',
            '001_duplicate.sql'  => 'CREATE TABLE b (id INT);',
        ]);

        $runner = new MigrationRunner($this->db, $dir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate migration version 1/');

        $runner->discover();
    }

    public function test_applies_pending_migrations_in_order_and_records_them(): void
    {
        $dir = $this->writeMigrationDir([
            '001_patients.sql' => 'CREATE TABLE patients (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pid VARCHAR(32));',
            '002_accounts.sql' => 'CREATE TABLE patient_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, patient_id INT UNSIGNED);',
        ]);

        $runner  = new MigrationRunner($this->db, $dir);
        $applied = $runner->migrate();

        self::assertCount(2, $applied);
        self::assertSame(1, $applied[0]['version']);
        self::assertSame(2, $applied[1]['version']);

        self::assertSame(1, $this->db->fetchInt(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = 'patients'",
            ['s' => $this->testDatabaseName],
        ));
        self::assertSame(1, $this->db->fetchInt(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = 'patient_accounts'",
            ['s' => $this->testDatabaseName],
        ));

        $recorded = $runner->applied();
        self::assertSame(['001_patients.sql', '002_accounts.sql'], array_values($recorded));
    }

    public function test_never_reapplies_an_already_applied_migration(): void
    {
        $dir = $this->writeMigrationDir([
            '001_once.sql' => 'CREATE TABLE t (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);',
        ]);

        $runner = new MigrationRunner($this->db, $dir);

        $first  = $runner->migrate();
        $second = $runner->migrate();

        self::assertCount(1, $first);
        self::assertCount(0, $second, 'a migration already recorded must never run twice');
    }

    public function test_a_failed_migration_stops_the_run_and_is_not_recorded(): void
    {
        $dir = $this->writeMigrationDir([
            '001_good.sql' => 'CREATE TABLE good_table (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);',
            // Deliberately invalid SQL - references a column that does not exist.
            '002_bad.sql'  => 'CREATE TABLE bad_table (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, UNIQUE KEY uq (nonexistent_column));',
            '003_never_reached.sql' => 'CREATE TABLE never_reached (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);',
        ]);

        $runner = new MigrationRunner($this->db, $dir);

        try {
            $runner->migrate();
            self::fail('Expected a RuntimeException from the failing migration.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('002_bad.sql', $e->getMessage());
        }

        // 001 succeeded and must be recorded; 002 must not be; 003 must never
        // have run at all (MIG-008: stop execution when a migration fails).
        $applied = $runner->applied();
        self::assertArrayHasKey(1, $applied);
        self::assertArrayNotHasKey(2, $applied);
        self::assertArrayNotHasKey(3, $applied);

        self::assertSame(1, $this->db->fetchInt(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = 'good_table'",
            ['s' => $this->testDatabaseName],
        ));
        self::assertSame(0, $this->db->fetchInt(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = 'never_reached'",
            ['s' => $this->testDatabaseName],
        ));

        // Re-running after the failure is fixed must resume exactly where it
        // stopped - 001 is not reapplied, 002 (now valid) and 003 do run.
        $dir2 = $this->writeMigrationDir([]);
        // Overwrite 002 with valid SQL and retry against the SAME database.
        file_put_contents($dir . '/002_bad.sql', 'CREATE TABLE bad_table (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);');

        $resumed = $runner->migrate();
        self::assertCount(2, $resumed, 'resuming must apply exactly the two still-pending migrations');
        self::assertSame(2, $resumed[0]['version']);
        self::assertSame(3, $resumed[1]['version']);
    }

    public function test_status_reports_applied_and_pending_migrations(): void
    {
        $dir = $this->writeMigrationDir([
            '001_applied.sql' => 'CREATE TABLE t1 (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);',
        ]);

        $runner = new MigrationRunner($this->db, $dir);
        $runner->migrate();

        file_put_contents($dir . '/002_pending.sql', 'CREATE TABLE t2 (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);');

        $status = $runner->status();

        self::assertCount(2, $status);
        self::assertTrue($status[0]['applied']);
        self::assertNotNull($status[0]['applied_at']);
        self::assertFalse($status[1]['applied']);
        self::assertNull($status[1]['applied_at']);
    }

    public function test_an_empty_migrations_directory_yields_nothing_pending(): void
    {
        $dir = $this->writeMigrationDir([]);

        $runner = new MigrationRunner($this->db, $dir);

        self::assertSame([], $runner->discover());
        self::assertSame([], $runner->migrate());
    }

    public function test_a_missing_migrations_directory_is_treated_as_empty_not_an_error(): void
    {
        $runner = new MigrationRunner($this->db, sys_get_temp_dir() . '/this_directory_does_not_exist_' . bin2hex(random_bytes(4)));

        self::assertSame([], $runner->discover());
    }

    public function test_a_multi_statement_migration_file_applies_every_statement(): void
    {
        $dir = $this->writeMigrationDir([
            '001_multi.sql' => "CREATE TABLE t1 (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);\n"
                . "-- a comment with an apostrophe, it's here on purpose\n"
                . "CREATE TABLE t2 (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, note VARCHAR(40) DEFAULT 'a; b');\n"
                . "INSERT INTO t1 (id) VALUES (1);",
        ]);

        $runner = new MigrationRunner($this->db, $dir);
        $runner->migrate();

        self::assertSame(1, $this->db->fetchInt('SELECT COUNT(*) FROM t1'));
        self::assertSame(2, $this->db->fetchInt(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME IN ('t1', 't2')",
            ['s' => $this->testDatabaseName],
        ));
    }
}
