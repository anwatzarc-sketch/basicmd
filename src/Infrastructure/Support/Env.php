<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

use RuntimeException;

/**
 * Minimal .env reader.
 *
 * Values are loaded into a private static array rather than into $_ENV or
 * putenv(). Two reasons: putenv() leaks secrets into the environment of any
 * child process the app spawns, and $_ENV is routinely dumped by debug
 * handlers and error pages. Keeping them here means a stray var_dump of the
 * superglobals cannot expose the database password.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException(
                "Environment file not found at {$path}. Copy .env.example to .env and configure it."
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            self::$values[trim($key)] = self::normalise($value);
        }

        self::$loaded = true;
    }

    /**
     * Strip surrounding quotes and any trailing inline comment.
     *
     * An unquoted value ends at the first " #", so `APP_ENV=production  # live`
     * yields "production". A quoted value keeps everything inside the quotes,
     * which matters for passwords that legitimately contain a hash.
     */
    private static function normalise(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if (($first === '"' || $first === "'") && str_ends_with($value, $first) && strlen($value) > 1) {
            return substr($value, 1, -1);
        }

        if (($pos = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $pos));
        }

        return $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    /**
     * @throws RuntimeException when a required key is absent or blank, so a
     *                          misconfigured deploy fails at boot rather than
     *                          halfway through a patient's booking.
     */
    public static function require(string $key): string
    {
        $value = self::$values[$key] ?? '';

        if ($value === '') {
            throw new RuntimeException("Required environment variable {$key} is missing or empty.");
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::$values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::$values[$key] ?? null;

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::$values[$key] ?? null;

        return $value === null || $value === '' ? $default : (float) $value;
    }

    /**
     * Split a comma-separated value, e.g. TRUSTED_HOSTS.
     *
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::$values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $v): bool => $v !== ''));
    }

    public static function has(string $key): bool
    {
        return isset(self::$values[$key]) && self::$values[$key] !== '';
    }

    /** Test-support hook. Never call from application code. */
    public static function reset(): void
    {
        self::$values = [];
        self::$loaded = false;
    }
}
