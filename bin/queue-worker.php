<?php

declare(strict_types=1);

/**
 * Email queue worker.
 *
 *     php bin/queue-worker.php [--limit=25] [--loop] [--quiet]
 *
 * Drains email_outbox. Intended to run from cron every few minutes:
 *
 *     * /5 * * * *  php /path/to/bin/queue-worker.php --quiet
 *
 * A lock file prevents overlapping runs: a slow SMTP server can make one
 * invocation outlast its cron interval, and two workers racing would risk
 * sending duplicates to patients.
 */

use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Logger;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$options = getopt('', ['limit::', 'loop', 'quiet', 'once']);

$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;
$quiet = isset($options['quiet']);
$loop  = isset($options['loop']);

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
$logger = $container->get(Logger::class)->withChannel('queue');

// ---------------------------------------------------------------------
//  Overlap lock
// ---------------------------------------------------------------------

$lockFile = $config->path('storage/cache/queue-worker.lock');
$lock     = @fopen($lockFile, 'c');

if ($lock === false) {
    fwrite(STDERR, "Could not open the lock file at {$lockFile}." . PHP_EOL);
    exit(1);
}

// Non-blocking: if another worker holds the lock, exit quietly rather than
// queueing up behind it and compounding the delay.
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    $say('Another worker is already running - exiting.');
    fclose($lock);
    exit(0);
}

// Released automatically when the process ends, including on a fatal.
register_shutdown_function(static function () use ($lock, $lockFile): void {
    @flock($lock, LOCK_UN);
    @fclose($lock);
    @unlink($lockFile);
});

// ---------------------------------------------------------------------
//  Drain
// ---------------------------------------------------------------------

/** @var MailQueue $queue */
$queue = $container->get(MailQueue::class);

$totals = ['sent' => 0, 'failed' => 0, 'retried' => 0];

do {
    $pending = $queue->countQueued();

    if ($pending === 0) {
        $say('Nothing queued.');
        break;
    }

    $say("Draining {$pending} queued message(s)...");

    $stats = $queue->drain($limit);

    foreach ($stats as $key => $count) {
        $totals[$key] += $count;
    }

    $say(sprintf('  sent: %d   retried: %d   failed: %d', $stats['sent'], $stats['retried'], $stats['failed']));

    // In --loop mode keep going until the queue is empty, pausing briefly so
    // a burst of retries does not hammer the SMTP server.
    if ($loop && $queue->countQueued() > 0) {
        sleep(2);
        continue;
    }

    break;
} while (true);

// Opportunistic housekeeping while we already hold a database connection.
try {
    /** @var RateLimiter $limiter */
    $limiter = $container->get(RateLimiter::class);
    $pruned  = $limiter->pruneExpired();

    if ($pruned > 0) {
        $say("Pruned {$pruned} expired rate-limit bucket(s).");
    }

    $prunedMail = $queue->pruneSent(30);

    if ($prunedMail > 0) {
        $say("Pruned {$prunedMail} delivered message(s) older than 30 days.");
    }
} catch (Throwable $e) {
    $logger->warning('Housekeeping failed', ['error' => $e->getMessage()]);
}

if ($totals['failed'] > 0) {
    $logger->warning('Queue run finished with failures', $totals);
}

$say(sprintf(
    'Done. sent: %d   retried: %d   failed: %d',
    $totals['sent'],
    $totals['retried'],
    $totals['failed'],
));

// Non-zero exit when messages failed permanently, so cron monitoring notices.
exit($totals['failed'] > 0 ? 1 : 0);
