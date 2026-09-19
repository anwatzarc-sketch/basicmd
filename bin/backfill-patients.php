<?php

declare(strict_types=1);

/**
 * Historical-booking patient report.
 *
 *     php bin/backfill-patients.php [--csv=path]
 *
 * This is deliberately a REPORT, not a data migration that creates rows in
 * `patients`. Here is why.
 *
 * FRS 5.2 marks patients.date_of_birth and patients.gender as Required
 * (NOT NULL in the schema) - and appointments, by design, never asked for
 * either. A patient who booked online gave a name, a phone number and
 * maybe an email; nothing more. There is no source of truth this script
 * could read a date of birth or gender FROM for a historical booking -
 * only a real conversation with the patient produces that, which is
 * exactly what EncounterService's check-in bridge already collects, at
 * the one moment a member of staff is actually talking to them.
 *
 * Manufacturing a placeholder (an arbitrary date of birth, or defaulting
 * gender to "other" to mean "unknown", which is not what that value
 * means) would put fabricated clinical data into the Master Patient Index
 * under the appearance of a real record - worse than no record at all.
 *
 * So: this script groups existing appointments by phone number (the same
 * E.164-normalised value PatientDeduplicationService itself matches on)
 * and reports how many distinct people are implied by the booking
 * history, with their most recent name and how many past bookings they
 * have - so staff know what to expect and can plan check-in capacity
 * accordingly. The actual `patients` row for each of them is created
 * correctly, with real answers, the first time they are checked in.
 */

use MediCareMini\Infrastructure\Container\Bootstrap;
use MediCareMini\Infrastructure\Persistence\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

const C_RESET = "\033[0m";
const C_BOLD  = "\033[1m";
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

$options = getopt('', ['csv::']);
$csvPath = $options['csv'] ?? null;

try {
    $container = Bootstrap::boot($basePath, cli: true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Boot failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$db = $container->get(Database::class);

// One row per distinct phone number, with the most recent booking's name
// (COALESCE-free: MySQL/MariaDB's ANY_VALUE-less "pick from the row with
// the max created_at" trick via a correlated ordering subquery keeps this
// portable without a window function, matching the rest of this codebase's
// SQL, which targets MariaDB 10.4 and avoids 8.0-only syntax throughout).
$rows = $db->fetchAll(
    "SELECT
        a.patient_phone AS phone,
        (SELECT a2.patient_name FROM appointments a2
         WHERE a2.patient_phone = a.patient_phone
         ORDER BY a2.created_at DESC LIMIT 1) AS most_recent_name,
        (SELECT a2.patient_email FROM appointments a2
         WHERE a2.patient_phone = a.patient_phone AND a2.patient_email IS NOT NULL
         ORDER BY a2.created_at DESC LIMIT 1) AS most_recent_email,
        COUNT(*) AS booking_count,
        MIN(a.created_at) AS first_booking_at,
        MAX(a.created_at) AS last_booking_at
     FROM appointments a
     WHERE a.deleted_at IS NULL
     GROUP BY a.patient_phone
     ORDER BY booking_count DESC, last_booking_at DESC",
);

// Cross-reference against patients already registered (e.g. via the
// check-in bridge) so this report only lists who is STILL missing.
$registeredPhones  = array_column(
    $db->fetchAll('SELECT phone_number FROM patients WHERE deleted_at IS NULL'),
    'phone_number',
);
$alreadyRegistered = array_flip($registeredPhones);

$missing = array_values(array_filter(
    $rows,
    static fn (array $r): bool => !isset($alreadyRegistered[$r['phone']]),
));

$line();
$line($paint('  Historical Booking Patients - Report', C_BOLD));
$line($paint('  ' . str_repeat('=', 48), C_DIM));
$line();
$line(sprintf('%d distinct phone number(s) in booking history.', count($rows)));
$line(sprintf('%d already have a patient record.', count($rows) - count($missing)));
$line(sprintf('%d have NOT been checked in yet - no patients row exists for them.', count($missing)));
$line();

if ($missing !== []) {
    $line($paint('  Not yet in the Master Patient Index:', C_BOLD));
    $line();

    foreach (array_slice($missing, 0, 30) as $r) {
        $line(sprintf(
            '  %-16s %-28s %2d booking(s), last %s',
            $r['phone'],
            mb_substr((string) $r['most_recent_name'], 0, 28),
            $r['booking_count'],
            $r['last_booking_at'],
        ));
    }

    if (count($missing) > 30) {
        $line($paint('  ... and ' . (count($missing) - 30) . ' more (use --csv to export the full list).', C_DIM));
    }
}

if ($csvPath !== null && $csvPath !== false) {
    $handle = fopen($csvPath, 'w');

    if ($handle === false) {
        fwrite(STDERR, "Could not open {$csvPath} for writing." . PHP_EOL);
        exit(1);
    }

    fputcsv($handle, ['phone', 'most_recent_name', 'most_recent_email', 'booking_count', 'first_booking_at', 'last_booking_at']);

    foreach ($missing as $r) {
        fputcsv($handle, [
            $r['phone'],
            $r['most_recent_name'],
            $r['most_recent_email'] ?? '',
            $r['booking_count'],
            $r['first_booking_at'],
            $r['last_booking_at'],
        ]);
    }

    fclose($handle);

    $line();
    $line($paint('  Exported ' . count($missing) . ' row(s) to ' . $csvPath, C_DIM));
}

$line();
