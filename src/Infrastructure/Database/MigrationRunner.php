<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Database;

use MediCareMini\Infrastructure\Persistence\Database;
use RuntimeException;

/**
 * Sequential SQL migration runner.
 *
 * The project shipped without one: schema.sql was the entire schema, applied
 * whole-file once at install. That was adequate for a single-table-set launch
 * and stops being adequate the moment a second schema change has to reach a
 * database that already holds real bookings. This class is the smallest thing
 * that closes that gap: discover numbered *.sql files, apply the ones not yet
 * recorded, in order, stopping on the first failure.
 *
 * DDL and transactions - a MariaDB/MySQL specific hazard
 * --------------------------------------------------------------------------
 * CREATE/ALTER/DROP TABLE cause an implicit commit on MySQL and MariaDB. If a
 * migration file were wrapped in Database::transaction(), a CREATE TABLE
 * statement partway through would silently end that transaction outside the
 * Database class's own bookkeeping - its SAVEPOINT depth counter would then
 * be wrong, and a later commit()/rollback() call would throw or (worse)
 * silently no-op. So migration SQL is executed directly, statement by
 * statement, OUTSIDE any transaction. A migration that fails partway through
 * therefore leaves whatever DDL already ran in place - this is the same
 * trade-off schema.sql already makes (DROP TABLE IF EXISTS / CREATE TABLE,
 * with no rollback story) and is why each migration should be written to be
 * safely re-runnable (IF NOT EXISTS / IF EXISTS) wherever practical.
 * Only the bookkeeping INSERT into schema_migrations is wrapped in a
 * transaction, since a plain INSERT never triggers an implicit commit.
 */
final class MigrationRunner
{
    private const string TABLE = 'schema_migrations';

    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * Discover *.sql files and return them sorted by numeric version prefix.
     *
     * @return list<array{version: int, filename: string, path: string}>
     */
    public function discover(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $out   = [];

        foreach ($files as $path) {
            $filename = basename($path);

            if (preg_match('/^(\d+)_.+\.sql$/', $filename, $m) !== 1) {
                // Deliberately strict: a file that does not follow the
                // NNN_description.sql convention is skipped rather than
                // guessed at, so a stray file cannot silently run as DDL.
                continue;
            }

            $out[] = [
                'version'  => (int) $m[1],
                'filename' => $filename,
                'path'     => $path,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['version'] <=> $b['version']);

        $seen = [];

        foreach ($out as $row) {
            if (isset($seen[$row['version']])) {
                throw new RuntimeException(sprintf(
                    'Duplicate migration version %d: "%s" and "%s".',
                    $row['version'],
                    $seen[$row['version']],
                    $row['filename'],
                ));
            }

            $seen[$row['version']] = $row['filename'];
        }

        return $out;
    }

    /** Create the tracking table if it does not already exist. */
    public function ensureTrackingTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version`    INT UNSIGNED NOT NULL,
                `migration`  VARCHAR(190) NOT NULL,
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_schema_migrations_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return array<int, string> version => filename, for every applied migration */
    public function applied(): array
    {
        $this->ensureTrackingTable();

        return $this->db->fetchPairs(
            'SELECT version, migration FROM `' . self::TABLE . '` ORDER BY version ASC',
        );
    }

    /**
     * Migrations discovered on disk but not yet recorded as applied.
     *
     * @return list<array{version: int, filename: string, path: string}>
     */
    public function pending(): array
    {
        $applied = $this->applied();

        return array_values(array_filter(
            $this->discover(),
            static fn (array $row): bool => !isset($applied[$row['version']]),
        ));
    }

    /**
     * Status report: every discovered migration plus whether it is applied.
     *
     * @return list<array{version: int, filename: string, applied: bool, applied_at: ?string}>
     */
    public function status(): array
    {
        $this->ensureTrackingTable();

        $appliedRows = $this->db->fetchAll(
            'SELECT version, migration, applied_at FROM `' . self::TABLE . '` ORDER BY version ASC',
        );

        $appliedByVersion = [];

        foreach ($appliedRows as $row) {
            $appliedByVersion[(int) $row['version']] = (string) $row['applied_at'];
        }

        $out = [];

        foreach ($this->discover() as $row) {
            $out[] = [
                'version'    => $row['version'],
                'filename'   => $row['filename'],
                'applied'    => isset($appliedByVersion[$row['version']]),
                'applied_at' => $appliedByVersion[$row['version']] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Apply every pending migration, in order, stopping at the first failure.
     *
     * @return list<array{version: int, filename: string}> migrations actually applied this run
     * @throws RuntimeException wrapping the failure, naming which migration failed
     */
    public function migrate(): array
    {
        $this->ensureTrackingTable();

        $applied = [];

        foreach ($this->pending() as $row) {
            $this->applyOne($row);
            $applied[] = ['version' => $row['version'], 'filename' => $row['filename']];
        }

        return $applied;
    }

    /**
     * Apply exactly one migration file and record it.
     *
     * DDL runs outside a transaction (see class docblock). The bookkeeping
     * row is the only part wrapped in one, and is only written after every
     * statement in the file has executed without error - so a partially
     * applied migration is NEVER marked as successful (MIG-009).
     */
    private function applyOne(array $row): void
    {
        $sql = file_get_contents($row['path']);

        if ($sql === false) {
            throw new RuntimeException("Could not read migration file: {$row['filename']}");
        }

        $statements = SqlStatementSplitter::split($sql);

        if ($statements === []) {
            throw new RuntimeException("Migration {$row['filename']} contains no statements.");
        }

        try {
            foreach ($statements as $statement) {
                $this->db->execute($statement);
            }
        } catch (\Throwable $e) {
            throw new RuntimeException(
                sprintf(
                    'Migration %s (version %d) failed: %s',
                    $row['filename'],
                    $row['version'],
                    $e->getMessage(),
                ),
                previous: $e,
            );
        }

        // A plain INSERT never triggers MySQL/MariaDB's implicit-commit
        // behaviour, so this is safe to wrap even though the DDL above was
        // deliberately not.
        $this->db->transaction(function (Database $db) use ($row): void {
            $db->execute(
                'INSERT INTO `' . self::TABLE . '` (version, migration) VALUES (:version, :migration)',
                ['version' => $row['version'], 'migration' => $row['filename']],
            );
        });
    }
}
