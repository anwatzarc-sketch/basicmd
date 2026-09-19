<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Support;

use DateTimeImmutable;
use Stringable;
use Throwable;

/**
 * Daily-rotating file logger with PSR-3 compatible severity levels.
 *
 * Deliberately dependency-free. Monolog would be the obvious choice on a
 * VPS, but this application is built to run on cPanel hosting where the
 * vendor directory is uploaded rather than installed, and every dependency
 * removed is one fewer thing that can break a deploy.
 *
 * PHI SAFETY: never pass patient names, phone numbers, notes or booking
 * references into $context. Log the appointment id instead - it is
 * meaningless without database access, whereas a log file is routinely
 * copied around during support work.
 */
final class Logger
{
    /** Severity ranking; a message below the configured threshold is dropped. */
    private const array LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    private int $threshold;

    public function __construct(
        private readonly string $directory,
        string $minimumLevel = 'warning',
        private readonly int $retentionDays = 30,
        private readonly string $channel = 'app',
    ) {
        $this->threshold = self::LEVELS[strtolower($minimumLevel)] ?? self::LEVELS['warning'];

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
    }

    /** A logger writing to a separate file, e.g. 'mail' or 'security'. */
    public function withChannel(string $channel): self
    {
        $clone = clone $this;

        return new self($this->directory, $this->levelName($this->threshold), $this->retentionDays, $channel);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log a caught exception with its class, location and trace.
     * The trace goes to the file only - never to the browser.
     */
    public function exception(Throwable $e, string $level = 'error', array $context = []): void
    {
        $this->log($level, $e->getMessage(), $context + [
            'exception' => $e::class,
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }

    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        $level    = strtolower($level);
        $severity = self::LEVELS[$level] ?? self::LEVELS['info'];

        if ($severity < $this->threshold) {
            return;
        }

        $line = sprintf(
            "[%s] %s.%s: %s%s%s",
            (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            $this->channel,
            strtoupper($level),
            $this->interpolate((string) $message, $context),
            $context === [] ? '' : ' ' . $this->encodeContext($context),
            PHP_EOL,
        );

        $file = sprintf('%s/%s-%s.log', $this->directory, $this->channel, date('Y-m-d'));

        // LOCK_EX keeps concurrent PHP-FPM workers from interleaving lines.
        // Failure to write must never take the request down with it.
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        // Prune on roughly 1 in 100 writes rather than every time.
        if (random_int(1, 100) === 1) {
            $this->prune();
        }
    }

    /** Replace {placeholders} in the message with scalar context values. */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    private function encodeContext(array $context): string
    {
        $encoded = json_encode(
            $context,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $encoded === false ? '[uncodable context]' : $encoded;
    }

    /** Delete log files older than the retention window. */
    private function prune(): void
    {
        $cutoff = time() - ($this->retentionDays * 86400);

        foreach (glob($this->directory . '/*.log') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function levelName(int $severity): string
    {
        return array_search($severity, self::LEVELS, true) ?: 'warning';
    }
}
