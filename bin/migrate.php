<?php

declare(strict_types=1);

/**
 * Migration runner CLI.
 *
 *     php bin/migrate.php              apply every pending migration
 *     php bin/migrate.php --status     show applied/pending without changing anything
 *     php bin/migrate.php --dry-run    list what WOULD run, without applying it
 *
 * database/migrations/NNN_description.sql files are applied in numeric
 * order and recorded in schema_migrations, one row per file, exactly once.
 * A failing migration stops the run immediately (MIG-008) and is never
 * recorded (MIG-009) - re-running this command after fixing the file picks
 * up exactly where it stopped.
 *
 * schema.sql remains the reference for a brand-new install (bin/install.php
 * still applies it directly, in one shot, which is the cheaper path for a
 * database with no rows in it yet). This command is for every database that
 * already has data in it - which, after the first migration ships, is every
 * database that matters.
 */

use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Database\MigrationRunner;
use Aster\Infrastructure\Persistence\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

const C_RESET = "\033[0m";
const C_BOLD  = "\033[1m";
const C_GREEN = "\033[32m";
const C_RED   = "\033[31m";
const C_AMBER = "\033[33m";
const C_DIM   = "\033[2m";

$supportsColour = (function (): bool {
    if (DIRECTORY_SEPARATOR === '\\') {
        return getenv('ANSICON') !== false
            || getenv('WT_SESSION') !== false
            || getenv('TERM_PROGRAM') === 'vscode'
            || str_contains((string) getenv('TERM'), 'xterm');
    }

    return function_exists('posix_isatty') && @posix_isatty(STDOUT);
})();

$paint = static fn (string $t, string $c): string => $supportsColour ? $c . $t . C_RESET : $t;
$line  = static function (string $t = ''): void { fwrite(STDOUT, $t . PHP_EOL); };
$ok    = static fn (string $t): string => $paint('  OK   ', C_GREEN) . $t;
$fail  = static fn (string $t): string => $paint(' FAIL  ', C_RED) . $t;
$pend  = static fn (string $t): string => $paint(' PEND  ', C_AMBER) . $t;

$options = getopt('', ['status', 'dry-run']);
$status  = isset($options['status']);
$dryRun  = isset($options['dry-run']);

try {
    $container = Bootstrap::boot($basePath, cli: true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Boot failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$db     = $container->get(Database::class);
$runner = new MigrationRunner($db, $basePath . '/database/migrations');

$line();
$line($paint('  Aster Medical Center - Migrations', C_BOLD));
$line($paint('  ' . str_repeat('=', 48), C_DIM));
$line();

if ($status || $dryRun) {
    $rows = $runner->status();

    if ($rows === []) {
        $line('No migration files found in database/migrations/.');
        exit(0);
    }

    foreach ($rows as $row) {
        if ($row['applied']) {
            $line($ok($row['filename'] . $paint('  (' . $row['applied_at'] . ')', C_DIM)));
        } else {
            $line($pend($row['filename']));
        }
    }

    $pendingCount = count(array_filter($rows, static fn (array $r): bool => !$r['applied']));

    $line();
    $line(sprintf('%d applied, %d pending.', count($rows) - $pendingCount, $pendingCount));

    exit(0);
}

$pending = $runner->pending();

if ($pending === []) {
    $line('Nothing to do - every migration is already applied.');
    exit(0);
}

$line(sprintf('%d pending migration(s):', count($pending)));

foreach ($pending as $row) {
    $line('  - ' . $row['filename']);
}

$line();

try {
    $applied = $runner->migrate();
} catch (Throwable $e) {
    $line($fail($e->getMessage()));
    $line();
    $line($paint('  Stopped. Nothing after the failing migration was applied.', C_RED));
    $line($paint('  Fix the migration file and re-run this command - already', C_DIM));
    $line($paint('  applied migrations will not be repeated.', C_DIM));
    exit(1);
}

foreach ($applied as $row) {
    $line($ok($row['filename']));
}

$line();
$line($paint(sprintf('  %d migration(s) applied.', count($applied)), C_GREEN));
