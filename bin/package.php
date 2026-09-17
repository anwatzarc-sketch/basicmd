<?php

declare(strict_types=1);

/**
 * Build a deployment bundle.
 *
 *     php bin/package.php [--out=build/deploy] [--zip]
 *
 * Copies only what the server actually needs to run. Everything used solely to
 * BUILD the project - node_modules, the Tailwind source and config, the font
 * downloader - is left behind, because the server has no Node and never
 * compiles anything.
 *
 * Two things are deliberately NOT included:
 *
 *   .env      Secrets must never travel in a bundle. Create it on the server
 *             from .env.example. Shipping it also risks overwriting the live
 *             one on a redeploy and pointing production at a dev database.
 *
 *   storage/  contents. The directory tree is created empty. Payment receipts
 *             and logs live there and belong to the server, not the bundle.
 *
 * Run `npm run build` first - the bundle ships compiled CSS and fonts, and
 * this script refuses to continue without them.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$root    = dirname(__DIR__);
$options = getopt('', ['out::', 'zip']);
$out     = $root . '/' . trim((string) ($options['out'] ?? 'build/deploy'), '/');
$makeZip = isset($options['zip']);

/** Directories copied wholesale. */
$directories = [
    'public'           => 'Front controller, .htaccess, compiled CSS/JS/fonts. THIS is the document root.',
    'src'              => 'Application code.',
    'resources/views'  => 'Templates. resources/css is build-source and is excluded.',
    'lang'             => 'English and Amharic dictionaries.',
    'bin'              => 'Installer, queue worker, reminder scheduler.',
    'vendor'           => 'Composer autoloader + PHPMailer. Shipped so the server needs no Composer.',
    'database'         => 'schema.sql and seed.sql. Needed once, at install.',
];

/** Individual files. */
$files = [
    'composer.json' => 'Lets you regenerate the autoloader on the server if you ever need to.',
    'composer.lock' => 'Pins the dependency versions that were tested.',
    '.env.example'  => 'Template. Copy to .env ON THE SERVER and fill in.',
    'README.md'     => 'Operations notes and the go-live checklist.',
];

/** Storage tree: created empty, must be writable by the web user. */
$storageDirs = [
    'storage/logs',
    'storage/cache',
    'storage/uploads/proofs',
    'storage/uploads/media',
];

/** Never copied, even if they appear inside a directory above. */
$excludePatterns = [
    '#/\.git(/|$)#',
    '#/node_modules(/|$)#',
    '#/\.DS_Store$#',
    '#/Thumbs\.db$#',
    // Runtime config an administrator has saved on the live server - a
    // redeploy must never clobber it, and a fresh bundle must never ship it.
    '#/public/CompanyBrand\.json$#',
];

// ---------------------------------------------------------------------

echo "\nBuilding deployment bundle\n";
echo str_repeat('=', 60) . "\n\n";

// Refuse to ship a bundle whose CSS was never compiled - the site would
// render unstyled and the cause would not be obvious on the server.
$builtCss = $root . '/public/dist/css/app.min.css';

if (!is_file($builtCss) || filesize($builtCss) < 1024) {
    fwrite(STDERR, "ERROR: public/dist/css/app.min.css is missing or empty.\n");
    fwrite(STDERR, "       Run `npm run build` before packaging.\n\n");
    exit(1);
}

printf("  compiled CSS present (%s KB)\n", number_format(filesize($builtCss) / 1024, 0));

$fonts = glob($root . '/public/dist/fonts/*.woff2') ?: [];

if ($fonts === []) {
    fwrite(STDOUT, "  WARNING: no self-hosted fonts found. Run `npm run fonts`.\n");
} else {
    printf("  %d self-hosted font(s)\n", count($fonts));
}

echo "\n";

// ---------------------------------------------------------------------

$excluded = static function (string $path) use ($excludePatterns): bool {
    $normalised = str_replace('\\', '/', $path);

    foreach ($excludePatterns as $pattern) {
        if (preg_match($pattern, $normalised) === 1) {
            return true;
        }
    }

    return false;
};

$copied = 0;
$bytes  = 0;

$copyTree = static function (string $from, string $to) use (&$copyTree, &$copied, &$bytes, $excluded): void {
    if ($excluded($from)) {
        return;
    }

    if (is_dir($from)) {
        if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
            throw new RuntimeException("Could not create {$to}");
        }

        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $copyTree($from . '/' . $entry, $to . '/' . $entry);
        }

        return;
    }

    if (!is_dir(dirname($to))) {
        mkdir(dirname($to), 0755, true);
    }

    copy($from, $to);

    $copied++;
    $bytes += (int) filesize($from);
};

// Start from a clean directory so a removed file does not linger.
if (is_dir($out)) {
    $rrmdir = static function (string $dir) use (&$rrmdir): void {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $rrmdir($path) : unlink($path);
        }

        rmdir($dir);
    };

    $rrmdir($out);
}

mkdir($out, 0755, true);

foreach ($directories as $directory => $why) {
    $source = $root . '/' . $directory;

    if (!is_dir($source)) {
        printf("  SKIP  %-18s (not found)\n", $directory);
        continue;
    }

    $before = $copied;
    $copyTree($source, $out . '/' . $directory);

    printf("  copy  %-18s %5d files   %s\n", $directory, $copied - $before, $why);
}

foreach ($files as $file => $why) {
    if (!is_file($root . '/' . $file)) {
        printf("  SKIP  %-18s (not found)\n", $file);
        continue;
    }

    copy($root . '/' . $file, $out . '/' . $file);
    $copied++;
    $bytes += (int) filesize($root . '/' . $file);

    printf("  copy  %-18s %5s          %s\n", $file, '1 file', $why);
}

// Storage: structure only, plus the deny-all guards.
foreach ($storageDirs as $directory) {
    mkdir($out . '/' . $directory, 0775, true);
    file_put_contents($out . '/' . $directory . '/.gitkeep', '');
}

$denyAll = <<<'HTACCESS'
# Defence in depth: this tree holds payment receipts and logs and must never be
# served. The document root is public/, so this should already be unreachable -
# but a misconfigured vhost is a common deployment mistake.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS;

foreach (['storage', 'storage/uploads', 'storage/uploads/proofs', 'storage/logs'] as $directory) {
    file_put_contents($out . '/' . $directory . '/.htaccess', $denyAll);
}

printf("  make  %-18s %5s          Empty, writable. Receipts and logs live here.\n", 'storage/', '4 dirs');

// A .env in the bundle would be a secret leak and could clobber the live one.
if (is_file($out . '/.env')) {
    unlink($out . '/.env');
}

echo "\n" . str_repeat('=', 60) . "\n";
printf("  %d files, %s MB\n", $copied, number_format($bytes / 1048576, 1));
printf("  %s\n", str_replace('\\', '/', $out));

if ($makeZip) {
    $zipPath = $out . '.zip';

    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "\n  ext-zip is unavailable; skipping the archive.\n");
    } else {
        @unlink($zipPath);

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($out) + 1);
            $relative = str_replace('\\', '/', $relative);

            $item->isDir()
                ? $zip->addEmptyDir($relative)
                : $zip->addFile($item->getPathname(), $relative);
        }

        $zip->close();

        printf("  %s  (%s MB)\n", str_replace('\\', '/', $zipPath), number_format(filesize($zipPath) / 1048576, 1));
    }
}

echo str_repeat('=', 60) . "\n\n";
echo "  Next, on the server:\n";
echo "    1. Set the domain's document root to the bundle's  public/  directory\n";
echo "    2. cp .env.example .env   and fill in DB_*, MAIL_*, APP_URL, TRUSTED_HOSTS\n";
echo "    3. chmod -R 775 storage/  (owner: the web user)\n";
echo "    4. Import database/schema.sql then database/seed.sql\n";
echo "    5. php bin/install.php\n";
echo "    6. Add the two scheduled tasks (see README)\n\n";
