<?php

declare(strict_types=1);

/**
 * Page smoke test - every GET route, public and admin.
 *
 *     php bin/check-pages.php                          anonymous sweep only
 *     php bin/check-pages.php --seed-user              full sweep, temp admin
 *     php bin/check-pages.php --email=you@x --password=...
 *     php bin/check-pages.php --base=https://staging.example
 *
 * Answers one question after a refactor, a rename or a dependency bump:
 * does every page still render? A 500 on one admin screen is invisible
 * until somebody opens it, and "somebody" is usually a user.
 *
 * ROUTES ARE DISCOVERED, NOT LISTED. The route table is parsed out of
 * public/index.php on every run, so a page added next month is covered
 * without anyone remembering to add it here - which is the failure mode
 * of every hand-maintained smoke-test list. If the parse cannot make
 * sense of the file it fails loudly rather than quietly testing a subset.
 *
 * GET ONLY, by design. This proves pages render; it does not submit
 * forms, and nothing it does writes domain data. Signing in writes the
 * ordinary auth rows (audit_logs, a session) exactly as a real login
 * does, and --seed-user creates one account it then deletes.
 *
 * TWO PASSES:
 *   anonymous      public pages must render; admin pages must redirect
 *                  to the login screen. That second half is an
 *                  authorisation test, not a formality - a page that
 *                  answers 200 here is leaking.
 *   authenticated  runs only with credentials. Admin pages must render.
 *                  A 403 is reported separately: it means the account
 *                  lacks that permission, which is a fact about the
 *                  account, not a broken page.
 *
 * Exit code is non-zero when anything failed, so it drops into CI.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

use MediCareMini\Infrastructure\Persistence\Database;
use MediCareMini\Infrastructure\Persistence\UserRepository;
use MediCareMini\Infrastructure\Security\PasswordHasher;
use MediCareMini\Infrastructure\Support\Env;

Env::load($basePath . '/.env');

// ---------------------------------------------------------------------
//  Arguments
// ---------------------------------------------------------------------

$options = [
    'base'      => null,
    'email'     => null,
    'password'  => null,
    'seed-user' => false,
    'only'      => 'all',   // all | public | admin
    'no-color'  => false,
    'help'      => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $match) !== 1) {
        exit("Unknown argument: {$argument}\n");
    }

    [$name, $value] = [$match[1], $match[2] ?? true];

    if (!array_key_exists($name, $options)) {
        exit("Unknown option: --{$name}\n");
    }

    $options[$name] = $value;
}

if ($options['help'] !== false) {
    echo file_get_contents(__FILE__, false, null, 0, 1800);

    exit(0);
}

$colour = static function (string $text, string $code) use ($options): string {
    return $options['no-color'] !== false ? $text : "\033[{$code}m{$text}\033[0m";
};

$green = static fn (string $t): string => $colour($t, '32');
$red   = static fn (string $t): string => $colour($t, '31');
$amber = static fn (string $t): string => $colour($t, '33');
$dim   = static fn (string $t): string => $colour($t, '2');
$bold  = static fn (string $t): string => $colour($t, '1');

$adminPath = trim((string) (Env::get('APP_ADMIN_PATH', 'admin') ?? 'admin'), '/');
$isProd    = Env::get('APP_ENV', 'local') === 'production';

// ---------------------------------------------------------------------
//  Route discovery
//
//  Both registration styles are literal in public/index.php:
//    $router->get('/path', ...)        top level, public
//    $r->get('/path', ...)             inside the one admin group
//
//  The single-group assumption is asserted rather than assumed: if a
//  second group ever appears, every route inside it would be silently
//  mis-prefixed, and a smoke test that quietly tests the wrong URLs is
//  worse than one that stops.
// ---------------------------------------------------------------------

$frontController = (string) file_get_contents($basePath . '/public/index.php');
$groupCount      = substr_count($frontController, '->group(');

if ($groupCount !== 1) {
    fwrite(STDERR, $red(sprintf(
        "public/index.php declares %d route group(s); this script understands exactly one (the admin group).\n"
        . "Teach it the new grouping before trusting its output.\n",
        $groupCount,
    )));

    exit(2);
}

// The trailing capture is the rest of the declaration, which names the
// middleware stack. Classifying on that rather than on the URL is what
// tells a patient-portal page apart from a public one: both are
// registered on $router at a /patient/... path, and only the stack says
// one of them requires a signed-in patient.
preg_match_all('/\$(router|r)->get\(\s*\'([^\']+)\'([^\n]*)/', $frontController, $matches, PREG_SET_ORDER);

if ($matches === []) {
    fwrite(STDERR, $red("No GET routes found in public/index.php - the parse failed.\n"));

    exit(2);
}

/** @var list<array{path:string, area:string}> $routes */
$routes = [];

foreach ($matches as $match) {
    $area = match (true) {
        $match[1] === 'r'                       => 'admin',
        str_contains($match[3], 'portalStack')  => 'portal',
        default                                 => 'public',
    };

    $routes[] = [
        'path' => $area === 'admin' ? '/' . $adminPath . $match[2] : $match[2],
        'area' => $area,
    ];
}

// ---------------------------------------------------------------------
//  Placeholder resolution
//
//  A route like /patients/{id:\d+} is only testable against a row that
//  exists. Each is resolved from the live database; anything with no row
//  behind it is SKIPPED and said so, because "no patients yet" is not a
//  broken page.
// ---------------------------------------------------------------------

/** @var array<string, array{sql:string, why:string}> keyed by the route path AS WRITTEN */
$resolvers = [
    '/services/{slug}'          => ['sql' => "SELECT slug FROM services WHERE status = 'active' AND slug IS NOT NULL LIMIT 1", 'why' => 'an active service'],
    '/doctors/{slug}'           => ['sql' => "SELECT slug FROM doctors WHERE status = 'active' AND slug IS NOT NULL LIMIT 1", 'why' => 'an active doctor'],
    '/health/{slug}'            => ['sql' => "SELECT slug FROM articles WHERE status = 'published' AND slug IS NOT NULL LIMIT 1", 'why' => 'a published article'],
    '/booking/{reference}'      => ['sql' => 'SELECT booking_ref FROM appointments WHERE deleted_at IS NULL LIMIT 1', 'why' => 'a booking'],
    '/booking/{reference}/pay'  => ['sql' => 'SELECT booking_ref FROM appointments WHERE deleted_at IS NULL LIMIT 1', 'why' => 'a booking'],

    '/appointments/{id:\d+}'        => ['sql' => 'SELECT id FROM appointments WHERE deleted_at IS NULL LIMIT 1', 'why' => 'an appointment'],
    '/payments/{id:\d+}'            => ['sql' => 'SELECT id FROM payments LIMIT 1', 'why' => 'a payment'],
    '/payments/{id:\d+}/proof'      => ['sql' => 'SELECT id FROM payments WHERE proof_path IS NOT NULL LIMIT 1', 'why' => 'a payment carrying a receipt'],
    '/doctors/{id:\d+}/edit'        => ['sql' => 'SELECT id FROM doctors LIMIT 1', 'why' => 'a doctor'],
    '/services/{id:\d+}/edit'       => ['sql' => 'SELECT id FROM services LIMIT 1', 'why' => 'a service'],
    '/packages/{id:\d+}/edit'       => ['sql' => 'SELECT id FROM health_packages LIMIT 1', 'why' => 'a health package'],
    '/facilities/{id:\d+}/edit'     => ['sql' => 'SELECT id FROM facilities LIMIT 1', 'why' => 'a facility'],
    '/articles/{id:\d+}/edit'       => ['sql' => 'SELECT id FROM articles LIMIT 1', 'why' => 'an article'],
    '/inquiries/{id:\d+}'           => ['sql' => 'SELECT id FROM contact_inquiries LIMIT 1', 'why' => 'an enquiry'],
    '/users/{id:\d+}/edit'          => ['sql' => 'SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1', 'why' => 'a staff account'],
    '/roles/{id:\d+}/edit'          => ['sql' => 'SELECT id FROM roles LIMIT 1', 'why' => 'a role'],
    '/wards/{id:\d+}/edit'          => ['sql' => 'SELECT id FROM ward_locations LIMIT 1', 'why' => 'a ward location'],
    '/patients/{id:\d+}'            => ['sql' => 'SELECT id FROM patients WHERE deleted_at IS NULL LIMIT 1', 'why' => 'a patient'],
    '/encounters/{id:\d+}'          => ['sql' => 'SELECT id FROM encounters LIMIT 1', 'why' => 'an encounter'],
    '/lab/orders/{id:\d+}/results'  => ['sql' => "SELECT id FROM diagnostic_orders WHERE category = 'Lab' LIMIT 1", 'why' => 'a lab order'],
    '/lab/orders/{id:\d+}/report'   => ['sql' => "SELECT id FROM diagnostic_orders WHERE category = 'Lab' AND results_payload_encrypted IS NOT NULL LIMIT 1", 'why' => 'a resulted lab order'],
    '/lab/panels/{code:[A-Za-z0-9_-]+}/parameters' => ['sql' => 'SELECT panel_code FROM lab_panels LIMIT 1', 'why' => 'a lab panel'],
    '/lab/catalog/{id:\d+}/edit'    => ['sql' => 'SELECT id FROM lab_panels LIMIT 1', 'why' => 'a lab panel'],
];

/**
 * Routes deliberately left alone, with the reason. /media/{path} serves
 * an uploaded file rather than a page, and walking it proves nothing
 * about rendering.
 */
$excluded = [
    '/media/{path:.+}' => 'serves an uploaded file, not a page',
];

/**
 * Where a non-200 is the correct answer.
 *
 * Keys are the route path EXACTLY as discovered, placeholder pattern and
 * all - '/payments/{id}/proof' silently matches nothing, because the
 * route is registered as '/payments/{id:\d+}/proof'.
 *
 * @var array<string, array{statuses: list<int>, why: string}>
 */
$expected = [
    '/api/availability' => ['statuses' => [200, 422], 'why' => 'validates its query string'],
    '/api/quote'        => ['statuses' => [200, 422], 'why' => 'validates its query string'],
    '/' . $adminPath . '/payments/{id:\d+}/proof' => [
        'statuses' => [200, 404],
        'why'      => 'the stored receipt file is missing from disk',
    ],
];

$database = Database::fromEnv();

$resolve = static function (string $path, string $area) use ($resolvers, $database, $adminPath): array {
    if (!str_contains($path, '{')) {
        return ['url' => $path, 'skip' => null];
    }

    // The resolver map is keyed by the path as written in index.php, so
    // strip the admin prefix this script added back off.
    $key = $area === 'admin' ? substr($path, strlen('/' . $adminPath)) : $path;

    if (!isset($resolvers[$key])) {
        return ['url' => null, 'skip' => 'no resolver for its placeholder'];
    }

    try {
        $value = $database->fetchValue($resolvers[$key]['sql']);
    } catch (Throwable $e) {
        return ['url' => null, 'skip' => 'lookup failed: ' . $e->getMessage()];
    }

    if ($value === null || $value === '') {
        return ['url' => null, 'skip' => 'needs ' . $resolvers[$key]['why'] . ' in the database'];
    }

    // One placeholder per segment; replace the whole {...} token.
    return [
        'url'  => (string) preg_replace('/\{[^}]+\}/', rawurlencode((string) $value), $path, 1),
        'skip' => null,
    ];
};

// ---------------------------------------------------------------------
//  Server
// ---------------------------------------------------------------------

$server = null;
$base   = is_string($options['base']) ? rtrim($options['base'], '/') : null;

if ($base === null) {
    // An ephemeral port, found by binding one and letting go, so two
    // runs in parallel (or a dev server already on 8000) never collide.
    $probe = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($probe === false) {
        exit("Could not find a free port: {$errstr}\n");
    }

    $port = (int) explode(':', (string) stream_socket_get_name($probe, false))[1];
    fclose($probe);

    $base = "http://127.0.0.1:{$port}";

    $command = sprintf(
        '%s -S 127.0.0.1:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        $port,
        escapeshellarg($basePath . '/public'),
        escapeshellarg($basePath . '/public/index.php'),
    );

    // NOT pipes. The built-in server logs one line per request to
    // stderr; with nobody draining that pipe it fills after a few dozen
    // requests and the server blocks mid-sweep, which looks exactly like
    // a hung page. A file has no such limit, and it is also where the
    // server's own startup errors end up if it never comes alive.
    $serverLog   = sys_get_temp_dir() . '/medicaremini-check-pages-' . getmypid() . '.log';
    $descriptors = [1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']];

    // bypass_shell keeps Windows from wrapping this in cmd.exe, which
    // would leave the real php.exe running after proc_terminate().
    $server = proc_open($command, $descriptors, $pipes, $basePath, null, ['bypass_shell' => true]);

    if (!is_resource($server)) {
        exit("Could not start the built-in server.\n");
    }

    // Wait for it to accept connections rather than sleeping a fixed
    // amount and hoping.
    $ready = false;

    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }

        usleep(100_000);
    }

    if (!$ready) {
        proc_terminate($server);

        exit("The built-in server did not come up. Its output:\n"
            . (is_file($serverLog) ? (string) file_get_contents($serverLog) : '(nothing logged)') . "\n");
    }

    printf("%s %s %s\n", $dim('server'), $base, $dim('(log: ' . $serverLog . ')'));
}

// ---------------------------------------------------------------------
//  HTTP, on streams so this needs no ext-curl
// ---------------------------------------------------------------------

/** @var array<string, string> $cookies */
$cookies = [];

$request = static function (string $url, ?array $post = null) use (&$cookies): array {
    $headers = ['Accept: text/html,application/json;q=0.9', 'User-Agent: medicaremini-check-pages'];

    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(
            static fn (string $k, string $v): string => $k . '=' . $v,
            array_keys($cookies),
            $cookies,
        ));
    }

    $http = [
        'method'          => $post === null ? 'GET' : 'POST',
        'header'          => $headers,
        'follow_location' => 0,       // a redirect is an assertion here, not a detour
        'ignore_errors'   => true,    // 4xx/5xx are results, not warnings
        'timeout'         => 15,
    ];

    if ($post !== null) {
        $http['header'][] = 'Content-Type: application/x-www-form-urlencoded';
        $http['content']  = http_build_query($post);
    }

    $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));

    // A dead server answers nothing, and grinding through eighty more
    // routes at fifteen seconds each to discover that helps nobody.
    static $unreachable = 0;

    if (!isset($http_response_header)) {
        if (++$unreachable >= 3) {
            fwrite(STDERR, "\nThe server stopped answering after {$unreachable} attempts; aborting.\n");

            exit(1);
        }

        return ['status' => 0, 'body' => '', 'location' => null];
    }

    $unreachable = 0;

    $status   = 0;
    $location = null;

    foreach ($http_response_header as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
            $status = (int) $m[1];           // last wins, which is the final response
        }

        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, 9));
        }

        if (stripos($line, 'Set-Cookie:') === 0
            && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m) === 1) {
            $cookies[trim($m[1])] = trim($m[2]);
        }
    }

    return ['status' => $status, 'body' => (string) $body, 'location' => $location];
};

// ---------------------------------------------------------------------
//  Optional temporary administrator
// ---------------------------------------------------------------------

$seeded   = null;
$email    = is_string($options['email']) ? $options['email'] : null;
$password = is_string($options['password']) ? $options['password'] : null;

if ($options['seed-user'] !== false) {
    if ($isProd) {
        exit($red("--seed-user is refused when APP_ENV=production. Pass real credentials instead.\n"));
    }

    if (!preg_match('#^https?://(127\.0\.0\.1|localhost)(:|/|$)#', $base)) {
        exit($red("--seed-user only runs against loopback. Pass real credentials for {$base}.\n"));
    }

    $users    = new UserRepository($database);
    $hasher   = new PasswordHasher(['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);
    $email    = 'check-pages+' . bin2hex(random_bytes(4)) . '@invalid.local';
    $password = 'Smoke#' . bin2hex(random_bytes(6)) . '!Aa1';

    $seeded = $users->create(
        fullName:     'Page Check (temporary)',
        email:        $email,
        passwordHash: $hasher->hash($password),
        role:         'super_admin',
    );

    printf("%s temporary admin #%d %s\n", $dim('seeded'), $seeded, $dim($email));
}

// Whatever happens below, the temporary account goes away.
$cleanup = static function () use (&$seeded, $database, $dim, $red): void {
    if ($seeded === null) {
        return;
    }

    try {
        // A hard delete, not the soft one: this row is test scaffolding,
        // not a person. Nothing a GET-only sweep touches holds it back -
        // audit_logs and the rest are ON DELETE SET NULL.
        $database->execute('DELETE FROM users WHERE id = :id', ['id' => $seeded]);
        $left = $database->fetchInt('SELECT COUNT(*) FROM users WHERE id = :id', ['id' => $seeded]);

        echo $left === 0
            ? $dim("removed temporary admin #{$seeded}\n")
            : $red("TEMPORARY ADMIN #{$seeded} COULD NOT BE REMOVED - delete it by hand\n");
    } catch (Throwable $e) {
        echo $red("failed to remove temporary admin #{$seeded}: " . $e->getMessage() . "\n");
    }

    $seeded = null;
};

/**
 * Stopping the server belongs in the shutdown handler, not only at the
 * bottom of the script: several paths above call exit() early, and each
 * one would otherwise leave a php -S process listening until the machine
 * is rebooted.
 */
$stopServer = static function () use (&$server): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
        $server = null;
    }
};

register_shutdown_function($cleanup);
register_shutdown_function($stopServer);

// ---------------------------------------------------------------------
//  Log watch - a page that renders a friendly error page still answers
//  500, but one that half-works may only show up in the log.
// ---------------------------------------------------------------------

$logFile   = $basePath . '/storage/logs/app-' . date('Y-m-d') . '.log';
$logOffset = is_file($logFile) ? (int) filesize($logFile) : 0;

// ---------------------------------------------------------------------
//  The sweep
// ---------------------------------------------------------------------

$pass = $fail = $skip = $note = 0;

$report = static function (string $verdict, string $path, string $detail = '') use (
    &$pass, &$fail, &$skip, &$note, $green, $red, $amber, $dim
): void {
    $label = match ($verdict) {
        'PASS' => $green('PASS'),
        'FAIL' => $red('FAIL'),
        'SKIP' => $dim('SKIP'),
        default => $amber('NOTE'),
    };

    match ($verdict) {
        'PASS' => $pass++,
        'FAIL' => $fail++,
        'SKIP' => $skip++,
        default => $note++,
    };

    printf("  %s  %-52s %s\n", $label, $path, $detail);
};

$loginPath = '/' . $adminPath . '/login';

$publicRoutes = array_values(array_filter($routes, static fn (array $r): bool => $r['area'] === 'public'));
$adminRoutes  = array_values(array_filter($routes, static fn (array $r): bool => $r['area'] === 'admin'));
$portalRoutes = array_values(array_filter($routes, static fn (array $r): bool => $r['area'] === 'portal'));

printf("\n%s  %d public, %d admin, %d patient portal\n",
    $bold('Discovered ' . count($routes) . ' GET routes:'),
    count($publicRoutes), count($adminRoutes), count($portalRoutes));

// --- Pass 1: anonymous ------------------------------------------------

if ($options['only'] !== 'admin') {
    echo "\n" . $bold('PUBLIC PAGES (anonymous)') . "\n";

    foreach ($publicRoutes as $route) {
        if (isset($excluded[$route['path']])) {
            $report('SKIP', $route['path'], $excluded[$route['path']]);

            continue;
        }

        $resolved = $resolve($route['path'], 'public');

        if ($resolved['url'] === null) {
            $report('SKIP', $route['path'], (string) $resolved['skip']);

            continue;
        }

        $rule    = $expected[$route['path']] ?? ['statuses' => [200], 'why' => ''];
        $response = $request($base . $resolved['url']);

        if ($response['status'] === 200) {
            $report('PASS', $resolved['url'], '200');
        } elseif (in_array($response['status'], $rule['statuses'], true)) {
            // Allowed, but not a rendered page - say so rather than
            // banking a green tick that hides a real regression later.
            $report('NOTE', $resolved['url'], sprintf('%d - %s', $response['status'], $rule['why']));
        } else {
            $report('FAIL', $resolved['url'], sprintf('got %d, wanted %s', $response['status'], implode('/', $rule['statuses'])));
        }
    }
}

if ($options['only'] !== 'admin' && $portalRoutes !== []) {
    echo "\n" . $bold('PATIENT PORTAL (anonymous - must not render)') . "\n";

    $portalLogin = '/patient/portal/login';

    foreach ($portalRoutes as $route) {
        $response = $request($base . $route['path']);

        if ($response['status'] === 302 && str_contains((string) $response['location'], $portalLogin)) {
            $report('PASS', $route['path'], 'redirects to the portal login');
        } elseif ($response['status'] === 302) {
            $report('NOTE', $route['path'], 'redirects to ' . (string) $response['location']);
        } else {
            $report('FAIL', $route['path'], sprintf('answered %d while signed out', $response['status']));
        }
    }

    echo '  ' . $dim('signed-in portal pages need a patient login, which this script does not take') . "\n";
}

if ($options['only'] !== 'public') {
    echo "\n" . $bold('ADMIN PAGES (anonymous - must not render)') . "\n";

    foreach ($adminRoutes as $route) {
        $resolved = $resolve($route['path'], 'admin');
        $url      = $resolved['url'] ?? preg_replace('/\{[^}]+\}/', '1', $route['path']);

        $response = $request($base . $url);

        if ($route['path'] === $loginPath) {
            $response['status'] === 200
                ? $report('PASS', $url, 'login screen renders (200)')
                : $report('FAIL', $url, sprintf('login screen got %d', $response['status']));

            continue;
        }

        if ($response['status'] === 302 && str_contains((string) $response['location'], $loginPath)) {
            $report('PASS', $url, 'redirects to login');
        } elseif ($response['status'] === 302) {
            $report('NOTE', $url, 'redirects to ' . (string) $response['location']);
        } else {
            $report('FAIL', $url, sprintf('answered %d while signed out', $response['status']));
        }
    }
}

// --- Pass 2: authenticated -------------------------------------------

if ($options['only'] !== 'public' && $email !== null && $password !== null) {
    echo "\n" . $bold('SIGNING IN') . "\n";

    $cookies = [];
    $page    = $request($base . $loginPath);

    preg_match('/name="csrf-token"\s+content="([^"]+)"/', $page['body'], $tokenMatch);
    $token = $tokenMatch[1] ?? null;

    if ($token === null) {
        $report('FAIL', $loginPath, 'no CSRF token on the login page');
    } else {
        $login = $request($base . $loginPath, [
            '_token'   => $token,
            'email'    => $email,
            'password' => $password,
        ]);

        $signedIn = $login['status'] === 302 && !str_contains((string) $login['location'], 'login');

        $signedIn
            ? $report('PASS', $loginPath, 'signed in as ' . $email)
            : $report('FAIL', $loginPath, sprintf('login got %d -> %s', $login['status'], (string) $login['location']));

        if ($signedIn) {
            echo "\n" . $bold('ADMIN PAGES (signed in)') . "\n";

            foreach ($adminRoutes as $route) {
                if ($route['path'] === $loginPath) {
                    continue;   // already signed in; this one redirects away
                }

                $resolved = $resolve($route['path'], 'admin');

                if ($resolved['url'] === null) {
                    $report('SKIP', $route['path'], (string) $resolved['skip']);

                    continue;
                }

                $rule     = $expected[$route['path']] ?? ['statuses' => [200], 'why' => ''];
                $response = $request($base . $resolved['url']);

                if ($response['status'] === 200) {
                    $report('PASS', $resolved['url'], '200');
                } elseif (in_array($response['status'], $rule['statuses'], true)) {
                    $report('NOTE', $resolved['url'], sprintf('%d - %s', $response['status'], $rule['why']));
                } elseif ($response['status'] === 403) {
                    $report('NOTE', $resolved['url'], '403 - this account lacks the permission');
                } elseif ($response['status'] === 302) {
                    $report('NOTE', $resolved['url'], 'redirects to ' . (string) $response['location']);
                } else {
                    $report('FAIL', $resolved['url'], sprintf('got %d, wanted %s', $response['status'], implode('/', $rule['statuses'])));
                }
            }
        }
    }
} elseif ($options['only'] !== 'public') {
    echo "\n" . $amber('Authenticated pass skipped') . " - pass --seed-user, or --email= and --password=\n";
}

// ---------------------------------------------------------------------
//  Teardown and summary
// ---------------------------------------------------------------------

$cleanup();
$stopServer();

$logged = [];

if (is_file($logFile) && filesize($logFile) > $logOffset) {
    $handle = fopen($logFile, 'rb');

    if ($handle !== false) {
        fseek($handle, $logOffset);

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\.(CRITICAL|ERROR|ALERT|EMERGENCY):/', $line) === 1) {
                $logged[] = rtrim($line);
            }
        }

        fclose($handle);
    }
}

if ($logged !== []) {
    printf("\n%s %d error(s) written to the application log during this run:\n",
        $red('LOG:'), count($logged));

    foreach (array_slice($logged, 0, 5) as $line) {
        echo '  ' . mb_substr($line, 0, 200) . "\n";
    }

    if (count($logged) > 5) {
        printf("  ... and %d more in %s\n", count($logged) - 5, $logFile);
    }
}

printf("\n%s  %s   %s   %s   %s\n",
    $bold('Result:'),
    $green($pass . ' passed'),
    $fail > 0 ? $red($fail . ' failed') : $dim('0 failed'),
    $note > 0 ? $amber($note . ' noted') : $dim('0 noted'),
    $dim($skip . ' skipped'),
);

exit($fail > 0 || $logged !== [] ? 1 : 0);
