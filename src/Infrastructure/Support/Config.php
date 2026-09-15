<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

use Aster\Domain\Enum\Locale;
use DateTimeZone;

/**
 * Typed, read-only view over the environment.
 *
 * Every setting the application reads from .env is surfaced here as a named
 * accessor, so a typo becomes a fatal method-not-found at boot instead of a
 * silent null three layers down. Nothing else in the codebase calls Env
 * directly except the bootstrap.
 */
final readonly class Config
{
    public function __construct(
        public string $appName,
        public string $appEnv,
        public bool $appDebug,
        public string $appUrl,
        public string $adminPath,
        public DateTimeZone $timezone,
        public Locale $defaultLocale,
        public string $appKey,
        public string $basePath,
    ) {
    }

    public static function fromEnv(string $basePath): self
    {
        $env = strtolower(Env::get('APP_ENV', 'production') ?? 'production');

        return new self(
            appName:       Env::get('APP_NAME', 'Aster Medical Center') ?? 'Aster Medical Center',
            appEnv:        $env,
            // Debug output is force-disabled in production regardless of what
            // .env says. A stack trace on a medical site can expose patient
            // data in a query string or a bound parameter.
            appDebug:      $env !== 'production' && Env::bool('APP_DEBUG', false),
            appUrl:        rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? '', '/'),
            adminPath:     trim(Env::get('APP_ADMIN_PATH', 'admin') ?? 'admin', '/'),
            timezone:      new DateTimeZone(Env::get('APP_TIMEZONE', 'Africa/Addis_Ababa') ?? 'Africa/Addis_Ababa'),
            defaultLocale: Locale::fromRequest(Env::get('APP_LOCALE', 'en')),
            appKey:        Env::get('APP_KEY', '') ?? '',
            basePath:      rtrim($basePath, '/\\'),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    public function isLocal(): bool
    {
        return $this->appEnv === 'local';
    }

    /** Absolute path inside the project, e.g. path('storage/logs'). */
    public function path(string $relative = ''): string
    {
        $relative = ltrim($relative, '/\\');

        return $relative === ''
            ? $this->basePath
            : $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /** Absolute URL for a site-relative path. */
    public function url(string $path = ''): string
    {
        return $this->appUrl . '/' . ltrim($path, '/');
    }

    /** Absolute URL inside the admin portal. */
    public function adminUrl(string $path = ''): string
    {
        return $this->url($this->adminPath . '/' . ltrim($path, '/'));
    }

    // --- Booking rules --------------------------------------------------

    public function bookingLeadHours(): int
    {
        return max(0, Env::int('BOOKING_LEAD_HOURS', 12));
    }

    public function bookingHorizonDays(): int
    {
        return max(1, Env::int('BOOKING_HORIZON_DAYS', 60));
    }

    public function expressSurchargeRate(): float
    {
        return max(0.0, Env::float('EXPRESS_SURCHARGE_RATE', 0.20));
    }

    public function reminderLeadHours(): int
    {
        return max(1, Env::int('REMINDER_LEAD_HOURS', 24));
    }

    public function followupDelayHours(): int
    {
        return max(1, Env::int('FOLLOWUP_DELAY_HOURS', 48));
    }

    // --- Security -------------------------------------------------------

    public function sessionName(): string
    {
        return Env::get('SESSION_NAME', 'aster_session') ?? 'aster_session';
    }

    public function sessionIdleSeconds(): int
    {
        return max(60, Env::int('SESSION_LIFETIME_MIN', 45) * 60);
    }

    public function sessionAbsoluteSeconds(): int
    {
        return max(600, Env::int('SESSION_ABSOLUTE_HOURS', 8) * 3600);
    }

    public function sessionSecure(): bool
    {
        return Env::bool('SESSION_SECURE', true);
    }

    public function sessionSameSite(): string
    {
        $value = Env::get('SESSION_SAME_SITE', 'Lax') ?? 'Lax';

        return in_array($value, ['Lax', 'Strict', 'None'], true) ? $value : 'Lax';
    }

    /** @return array{memory_cost:int, time_cost:int, threads:int} */
    public function argonOptions(): array
    {
        return [
            'memory_cost' => max(16384, Env::int('ARGON_MEMORY_COST', 65536)),
            'time_cost'   => max(2, Env::int('ARGON_TIME_COST', 4)),
            'threads'     => max(1, Env::int('ARGON_THREADS', 2)),
        ];
    }

    public function loginMaxAttempts(): int
    {
        return max(1, Env::int('LOGIN_MAX_ATTEMPTS', 5));
    }

    public function loginLockoutMinutes(): int
    {
        return max(1, Env::int('LOGIN_LOCKOUT_MINUTES', 15));
    }

    /** @return list<string> */
    public function trustedHosts(): array
    {
        return Env::list('TRUSTED_HOSTS');
    }

    public function trustProxy(): bool
    {
        return Env::bool('TRUST_PROXY', false);
    }

    // --- Uploads --------------------------------------------------------

    public function proofDir(): string
    {
        return $this->path(Env::get('UPLOAD_PROOF_DIR', 'storage/uploads/proofs') ?? 'storage/uploads/proofs');
    }

    public function mediaDir(): string
    {
        return $this->path(Env::get('UPLOAD_MEDIA_DIR', 'storage/uploads/media') ?? 'storage/uploads/media');
    }

    public function proofMaxBytes(): int
    {
        return max(1, Env::int('UPLOAD_PROOF_MAX_MB', 5)) * 1024 * 1024;
    }

    public function mediaMaxBytes(): int
    {
        return max(1, Env::int('UPLOAD_MEDIA_MAX_MB', 8)) * 1024 * 1024;
    }

    public function webpQuality(): int
    {
        return min(100, max(40, Env::int('UPLOAD_WEBP_QUALITY', 82)));
    }

    // --- Assets & maps --------------------------------------------------

    public function assetsBuilt(): bool
    {
        // Production always uses the compiled bundle; the CDN fallback is a
        // development convenience and must never ship to patients.
        return $this->isProduction() || Env::bool('ASSETS_BUILT', true);
    }

    public function googleMapsKey(): string
    {
        return Env::get('GOOGLE_MAPS_API_KEY', '') ?? '';
    }

    /** @return array{lat:string, lng:string, zoom:int} */
    public function mapCoordinates(): array
    {
        return [
            'lat'  => Env::get('MAP_LATITUDE', '8.9969') ?? '8.9969',
            'lng'  => Env::get('MAP_LONGITUDE', '38.7869') ?? '38.7869',
            'zoom' => Env::int('MAP_ZOOM', 16),
        ];
    }

    public function logLevel(): string
    {
        return strtolower(Env::get('LOG_LEVEL', 'warning') ?? 'warning');
    }

    public function logRetentionDays(): int
    {
        return max(1, Env::int('LOG_MAX_FILES', 30));
    }
}
