<?php

declare(strict_types=1);

/**
 * Appointment reminder and follow-up scheduler.
 *
 *     php bin/send-reminders.php [--dry-run] [--quiet]
 *
 * Run hourly from cron:
 *
 *     0 * * * *  php /path/to/bin/send-reminders.php --quiet
 *
 * This is the retention engine the brief specified as SMS, running on email.
 * Three jobs, in order:
 *
 *   1. Queue the 24-hour reminder for confirmed appointments.
 *   2. Queue the post-visit follow-up for completed ones.
 *   3. Mark yesterday's unattended confirmed appointments as no-shows, so the
 *      dashboard and capacity figures stay truthful.
 *
 * Messages are only ENQUEUED here. queue-worker.php delivers them, which
 * keeps the scheduling logic independent of SMTP availability.
 */

use MediCareMini\Application\Service\NotificationService;
use MediCareMini\Infrastructure\Container\Bootstrap;
use MediCareMini\Infrastructure\Persistence\AppointmentRepository;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Logger;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$options = getopt('', ['dry-run', 'quiet']);
$dryRun  = isset($options['dry-run']);
$quiet   = isset($options['quiet']);

$say = static function (string $message) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
    }
};

try {
    $container = Bootstrap::boot($basePath, cli: true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Boot failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var Config $config */
$config = $container->get(Config::class);

/** @var Logger $logger */
$logger = $container->get(Logger::class)->withChannel('reminders');

/** @var AppointmentRepository $appointments */
$appointments = $container->get(AppointmentRepository::class);

/** @var NotificationService $notifications */
$notifications = $container->get(NotificationService::class);

// Overlap lock: reminders are the one job where a double run means a patient
// receives the same message twice.
$lockFile = $config->path('storage/cache/reminders.lock');
$lock     = @fopen($lockFile, 'c');

if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $say('Another reminder run is in progress - exiting.');
    exit(0);
}

register_shutdown_function(static function () use ($lock, $lockFile): void {
    @flock($lock, LOCK_UN);
    @fclose($lock);
    @unlink($lockFile);
});

if ($dryRun) {
    $say('DRY RUN - nothing will be queued or modified.');
}

$stats = ['reminders' => 0, 'followups' => 0, 'no_shows' => 0, 'errors' => 0];

// ---------------------------------------------------------------------
//  1. 24-hour reminders
// ---------------------------------------------------------------------

$leadHours = $config->reminderLeadHours();
$due       = $appointments->dueForReminder($leadHours);

$say(sprintf('Found %d appointment(s) due a reminder (%dh window).', count($due), $leadHours));

foreach ($due as $appointment) {
    try {
        if (!$dryRun) {
            $notifications->appointmentReminder($appointment);

            // Stamped only after a successful enqueue, so a failure here
            // leaves the appointment eligible for the next run rather than
            // silently skipping the patient.
            $appointments->markReminderSent($appointment->id);
        }

        $stats['reminders']++;
        $say('  reminder -> ' . $appointment->reference->value);
    } catch (Throwable $e) {
        $stats['errors']++;
        $logger->error('Reminder failed', [
            'appointment_id' => $appointment->id,
            'error'          => $e->getMessage(),
        ]);
    }
}

// ---------------------------------------------------------------------
//  2. Post-visit follow-ups
// ---------------------------------------------------------------------

$delayHours = $config->followupDelayHours();
$followups  = $appointments->dueForFollowup($delayHours);

$say(sprintf('Found %d completed visit(s) due a follow-up (%dh after).', count($followups), $delayHours));

foreach ($followups as $appointment) {
    try {
        if (!$dryRun) {
            $notifications->appointmentFollowup($appointment);
            $appointments->markFollowupSent($appointment->id);
        }

        $stats['followups']++;
        $say('  follow-up -> ' . $appointment->reference->value);
    } catch (Throwable $e) {
        $stats['errors']++;
        $logger->error('Follow-up failed', [
            'appointment_id' => $appointment->id,
            'error'          => $e->getMessage(),
        ]);
    }
}

// ---------------------------------------------------------------------
//  3. Auto no-show
// ---------------------------------------------------------------------

if (!$dryRun) {
    try {
        $stats['no_shows'] = $appointments->autoMarkNoShows(graceHours: 6);

        if ($stats['no_shows'] > 0) {
            $say(sprintf('Marked %d past appointment(s) as no-show.', $stats['no_shows']));
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        $logger->error('No-show sweep failed', ['error' => $e->getMessage()]);
    }
}

// ---------------------------------------------------------------------
//  Summary
// ---------------------------------------------------------------------

$say(sprintf(
    'Done. reminders: %d   follow-ups: %d   no-shows: %d   errors: %d',
    $stats['reminders'],
    $stats['followups'],
    $stats['no_shows'],
    $stats['errors'],
));

if ($stats['reminders'] > 0 || $stats['followups'] > 0) {
    $logger->info('Reminder run complete', $stats);
}

exit($stats['errors'] > 0 ? 1 : 0);
