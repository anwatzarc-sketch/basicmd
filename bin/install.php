<?php

declare(strict_types=1);

/**
 * Installer.
 *
 *     php bin/install.php
 *
 * Checks the environment, verifies the database connection and schema, and
 * creates the first SuperAdmin account interactively.
 *
 * The first admin is created here rather than seeded because shipping a known
 * password hash in seed.sql is how demo installations get compromised. The
 * password is typed by the person who will own the account, is read with
 * echo disabled where the platform supports it, and is never written to disk
 * or to a log.
 */

use Aster\Domain\Enum\UserRole;
use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\PasswordHasher;
use Aster\Infrastructure\Support\Config;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This installer can only be run from the command line.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

// ---------------------------------------------------------------------
//  Terminal helpers
// ---------------------------------------------------------------------

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

$paint = static function (string $text, string $colour) use ($supportsColour): string {
    return $supportsColour ? $colour . $text . C_RESET : $text;
};

$line = static function (string $text = '') use ($paint): void {
    fwrite(STDOUT, $text . PHP_EOL);
};

$ok   = static fn (string $t): string => $paint('  OK   ', C_GREEN) . $t;
$fail = static fn (string $t): string => $paint(' FAIL  ', C_RED) . $t;
$warn = static fn (string $t): string => $paint(' WARN  ', C_AMBER) . $t;

$ask = static function (string $prompt, ?string $default = null): string {
    $suffix = $default !== null ? " [{$default}]" : '';
    fwrite(STDOUT, $prompt . $suffix . ': ');

    $answer = trim((string) fgets(STDIN));

    return $answer === '' && $default !== null ? $default : $answer;
};

/**
 * Read a password without echoing it.
 *
 * Falls back to visible input where the platform offers no way to disable
 * echo, but says so first rather than silently exposing the password.
 */
$askSecret = static function (string $prompt) use ($line, $paint): string {
    fwrite(STDOUT, $prompt . ': ');

    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        @shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);

        return $value;
    }

    // Windows: VBScript trick is unreliable across hosts, so be honest.
    $value = trim((string) fgets(STDIN));
    $line($paint('       (input was visible on this platform - clear your terminal history)', C_DIM));

    return $value;
};

// ---------------------------------------------------------------------
//  Banner
// ---------------------------------------------------------------------

$line();
$line($paint('  Aster Medical Center - Installer', C_BOLD));
$line($paint('  ' . str_repeat('=', 48), C_DIM));
$line();

// ---------------------------------------------------------------------
//  1. Environment file
// ---------------------------------------------------------------------

$line($paint('1. Environment', C_BOLD));

if (!is_file($basePath . '/.env')) {
    $line($fail('.env not found.'));
    $line('       Copy .env.example to .env and configure it first:');
    $line($paint('         cp .env.example .env', C_DIM));
    exit(1);
}

try {
    $container = Bootstrap::boot($basePath, cli: true);
} catch (Throwable $e) {
    $line($fail('Could not boot: ' . $e->getMessage()));
    exit(1);
}

/** @var Config $config */
$config = $container->get(Config::class);

$line($ok('.env loaded'));

// APP_KEY is used for anything that needs a server-side secret; a blank one
// is a silent weakness, so it is generated rather than merely warned about.
if ($config->appKey === '') {
    $key = base64_encode(random_bytes(32));
    $env = (string) file_get_contents($basePath . '/.env');

    $env = preg_match('/^APP_KEY=.*$/m', $env) === 1
        ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env)
        : $env . PHP_EOL . 'APP_KEY=' . $key . PHP_EOL;

    file_put_contents($basePath . '/.env', $env);
    $line($ok('APP_KEY generated'));
} else {
    $line($ok('APP_KEY present'));
}

if ($config->isProduction() && !$config->sessionSecure()) {
    $line($warn('SESSION_SECURE is false in production - session cookies will be sent over plain HTTP.'));
}

// ---------------------------------------------------------------------
//  2. PHP requirements
// ---------------------------------------------------------------------

$line();
$line($paint('2. PHP requirements', C_BOLD));

$problems = 0;

if (PHP_VERSION_ID < 80300) {
    $line($fail('PHP 8.3 or newer is required. Running ' . PHP_VERSION . '.'));
    $problems++;
} else {
    $line($ok('PHP ' . PHP_VERSION));
}

foreach (['pdo_mysql', 'mbstring', 'gd', 'fileinfo', 'openssl', 'json'] as $extension) {
    if (extension_loaded($extension)) {
        $line($ok('ext-' . $extension));
    } else {
        $line($fail('ext-' . $extension . ' is missing'));
        $problems++;
    }
}

if (!defined('PASSWORD_ARGON2ID')) {
    $line($fail('Argon2id is unavailable - this PHP build cannot hash passwords securely.'));
    $problems++;
} else {
    $line($ok('Argon2id available'));
}

if (function_exists('gd_info') && !(gd_info()['WebP Support'] ?? false)) {
    $line($warn('GD has no WebP support - uploaded images will not be converted.'));
}

if ($problems > 0) {
    $line();
    $line($paint('  Fix the failures above before continuing.', C_RED));
    exit(1);
}

// ---------------------------------------------------------------------
//  3. Writable directories
// ---------------------------------------------------------------------

$line();
$line($paint('3. Storage', C_BOLD));

foreach (['storage/logs', 'storage/cache', 'storage/uploads/proofs', 'storage/uploads/media'] as $relative) {
    $path = $config->path($relative);

    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }

    if (is_writable($path)) {
        $line($ok($relative . ' writable'));
    } else {
        $line($fail($relative . ' is not writable'));
        $problems++;
    }
}

// The proofs directory must not be reachable over HTTP. If it sits inside
// public/ something is badly misconfigured and patient receipts are exposed.
if (str_contains(str_replace('\\', '/', $config->proofDir()), '/public/')) {
    $line($fail('UPLOAD_PROOF_DIR is inside public/ - payment receipts would be publicly downloadable.'));
    $problems++;
}

if ($problems > 0) {
    exit(1);
}

// ---------------------------------------------------------------------
//  4. Database
// ---------------------------------------------------------------------

$line();
$line($paint('4. Database', C_BOLD));

/** @var Database $db */
$db = $container->get(Database::class);

try {
    $db->pdo();
    $line($ok('Connected'));
} catch (Throwable $e) {
    $line($fail($e->getMessage()));
    $line('       Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
    exit(1);
}

$required = [
    'users', 'doctors', 'services', 'health_packages', 'appointments',
    'payments', 'payment_methods', 'articles', 'contact_inquiries',
    'facilities', 'audit_logs', 'system_settings', 'email_outbox',
    'rate_limits', 'password_resets', 'doctor_time_off',
];

// information_schema rather than SHOW TABLES: the latter returns a column
// named after the database, which makes the result awkward to read portably.
$existing = array_map('strval', array_column(
    $db->fetchAll(
        'SELECT TABLE_NAME AS name
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()'
    ),
    'name',
));

$absent = array_diff($required, $existing);

if ($absent !== []) {
    $line($fail('Missing tables: ' . implode(', ', $absent)));
    $line('       Load the schema first:');
    $line($paint('         mysql -u USER -p DATABASE < database/schema.sql', C_DIM));
    $line($paint('         mysql -u USER -p DATABASE < database/seed.sql', C_DIM));
    exit(1);
}

$line($ok(count($required) . ' tables present'));

$serviceCount = $db->fetchInt('SELECT COUNT(*) FROM services');

if ($serviceCount === 0) {
    $line($warn('No services found - you may not have loaded database/seed.sql.'));
} else {
    $line($ok($serviceCount . ' services seeded'));
}

// ---------------------------------------------------------------------
//  5. First administrator
// ---------------------------------------------------------------------

$line();
$line($paint('5. Administrator account', C_BOLD));

/** @var UserRepository $users */
$users = $container->get(UserRepository::class);

/** @var PasswordHasher $hasher */
$hasher = $container->get(PasswordHasher::class);

if (!$users->isEmpty()) {
    $line($ok('Staff accounts already exist - skipping account creation.'));
} else {
    $line($paint('   No staff accounts exist yet. Create the first administrator.', C_DIM));
    $line();

    $name = '';
    while ($name === '') {
        $name = $ask('   Full name');
    }

    $email = '';
    while ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = strtolower($ask('   Email address'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $line($paint('       That is not a valid email address.', C_RED));
            $email = '';
        }
    }

    while (true) {
        $password = $askSecret('   Password (min ' . PasswordHasher::minimumLength() . ' chars)');

        try {
            PasswordHasher::assertStrong($password);
        } catch (Throwable $e) {
            foreach ((array) ($e instanceof \Aster\Domain\Exception\ValidationException ? $e->all() : [$e->getMessage()]) as $problem) {
                $line($paint('       - ' . $problem, C_RED));
            }

            continue;
        }

        $confirm = $askSecret('   Confirm password');

        if (!hash_equals($password, $confirm)) {
            $line($paint('       The passwords do not match.', C_RED));

            continue;
        }

        break;
    }

    $userId = $users->create(
        fullName:     $name,
        email:        $email,
        passwordHash: $hasher->hash($password),
        role:         UserRole::SUPER_ADMIN,
        status:       'active',
    );

    // Overwrite the plaintext in memory as soon as it is no longer needed.
    $password = str_repeat('\0', 64);
    unset($password, $confirm);

    $line();
    $line($ok('Administrator created (id ' . $userId . ')'));
}

// ---------------------------------------------------------------------
//  Done
// ---------------------------------------------------------------------

$line();
$line($paint('  Installation complete.', C_GREEN . C_BOLD));
$line();
$line('  Next steps:');
$line($paint('    1. Build the frontend assets:  npm install && npm run build', C_DIM));
$line($paint('    2. Add these cron entries:', C_DIM));
$line($paint('         */5 * * * *  php ' . $basePath . '/bin/queue-worker.php', C_DIM));
$line($paint('         0   * * * *  php ' . $basePath . '/bin/send-reminders.php', C_DIM));
$line($paint('    3. Point your web server document root at:  ' . $basePath . '/public', C_DIM));
$line($paint('    4. Sign in at:  ' . $config->adminUrl('login'), C_DIM));
$line();
