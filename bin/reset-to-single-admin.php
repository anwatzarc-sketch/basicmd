<?php

declare(strict_types=1);

/**
 * Pre-launch access reset.
 *
 *     php bin/reset-to-single-admin.php --preview
 *     php bin/reset-to-single-admin.php --name="Anwar Dev" --email=you@example.com [--password=...]
 *
 * Deletes every user, role, grant and role-permission row, then recreates a
 * single `super_admin` role holding the whole permission catalogue and one
 * user who holds it (spec §4.1's starting state).
 *
 * DESTRUCTIVE. See PlatformReset's docblock - in particular the LEDGER
 * EXCEPTION: this deletes consumption_ledger / receivable_payments rows,
 * which are insert-only by contract, because their accountant_id FK is
 * ON DELETE RESTRICT and would otherwise refuse the user delete. Only ever
 * run this against a deployment whose financial rows are test data.
 *
 * Without --password, a strong temporary one is generated and printed once;
 * the account is NOT flagged must-change-password, because the person
 * running this is the person who will own it.
 */

use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Database\PlatformReset;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\PasswordHasher;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$options = getopt('', ['preview', 'name::', 'email::', 'password::', 'force']);

$line = static function (string $text = ''): void {
    fwrite(STDOUT, $text . PHP_EOL);
};

try {
    $container = Bootstrap::boot($basePath, cli: true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Boot failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$db = $container->get(Database::class);

$reset = new PlatformReset(
    $db,
    $container->get(UserRepository::class),
    $container->get(PasswordHasher::class),
);

$line();
$line('  Pre-launch access reset');
$line('  ' . str_repeat('=', 56));
$line();
// Named explicitly so nobody wipes the wrong database by assuming which
// .env is loaded.
$line('  Database: ' . (string) $db->fetchValue('SELECT DATABASE()'));
$line();
$line('  This will DELETE:');

foreach ($reset->preview() as $table => $count) {
    $line(sprintf('    %-24s %d row(s)', $table, $count));
}

$line();

if (isset($options['preview'])) {
    $line('  --preview only: nothing was changed.');
    $line();
    exit(0);
}

$name  = trim((string) ($options['name'] ?? ''));
$email = trim((string) ($options['email'] ?? ''));

if ($name === '' || $email === '') {
    fwrite(STDERR, "  --name and --email are required (or use --preview).\n\n");
    exit(1);
}

$password  = (string) ($options['password'] ?? '');
$generated = false;

if ($password === '') {
    $password  = PasswordHasher::generateTemporary();
    $generated = true;
}

// A deliberate speed bump: this is unrecoverable, and typing the word is
// cheaper than restoring a database that should not have been wiped.
if (!isset($options['force'])) {
    $line('  Type RESET to continue, anything else to abort:');
    fwrite(STDOUT, '  > ');

    if (trim((string) fgets(STDIN)) !== 'RESET') {
        $line();
        $line('  Aborted. Nothing was changed.');
        $line();
        exit(1);
    }
}

try {
    $result = $reset->run($name, $email, $password);
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . '  FAILED: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, '  Nothing was committed - the whole reset runs in one transaction.' . PHP_EOL . PHP_EOL);
    exit(1);
}

$line();
$line('  Done.');
$line(sprintf('    role     #%d  %s (%d permissions)', $result['role_id'], PlatformReset::ROLE_SLUG, $result['permissions']));
$line(sprintf('    user     #%d  %s <%s>', $result['user_id'], $name, $email));

if ($generated) {
    $line();
    $line('    Temporary password (shown once, never logged):');
    $line('      ' . $password);
}

$line();
