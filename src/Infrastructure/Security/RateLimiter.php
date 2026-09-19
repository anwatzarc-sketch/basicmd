<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Security;

use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Infrastructure\Persistence\Database;
use DateTimeImmutable;

/**
 * Fixed-window rate limiter backed by the database.
 *
 * Database-backed rather than APCu or a file, because the limit has to hold
 * across every PHP-FPM worker and survive a deploy. A booking flood that only
 * had to beat per-process counters would sail straight through.
 *
 * The counter is maintained with a single atomic upsert, so two concurrent
 * requests cannot both read "4 hits" and both write "5".
 */
final readonly class RateLimiter
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Record one hit and report whether the caller is still under the limit.
     *
     * @param string $key         bucket identity, e.g. "login:203.0.113.9"
     * @param int    $maxAttempts hits permitted per window
     * @param int    $windowSeconds
     */
    public function attempt(string $key, int $maxAttempts, int $windowSeconds = 3600): bool
    {
        $bucket = $this->normaliseKey($key);
        $now    = new DateTimeImmutable();
        $expiry = $now->modify("+{$windowSeconds} seconds");

        $nowSql    = $now->format('Y-m-d H:i:s');
        $expirySql = $expiry->format('Y-m-d H:i:s');

        // One statement does all three cases: create the bucket, reset it if
        // the window has rolled over, or increment it if still inside.
        //
        // Every placeholder is distinct even where the value repeats: with
        // ATTR_EMULATE_PREPARES off these are real prepared statements, and
        // MySQL permits a given named placeholder exactly once.
        $this->db->execute(
            'INSERT INTO rate_limits (bucket_key, hits, window_start, expires_at)
             VALUES (:key, 1, :start, :expires)
             ON DUPLICATE KEY UPDATE
                hits         = IF(expires_at <= :now1, 1, hits + 1),
                window_start = IF(expires_at <= :now2, :start2, window_start),
                expires_at   = IF(expires_at <= :now3, :expires2, expires_at)',
            [
                'key'      => $bucket,
                'start'    => $nowSql,
                'expires'  => $expirySql,
                'now1'     => $nowSql,
                'now2'     => $nowSql,
                'now3'     => $nowSql,
                'start2'   => $nowSql,
                'expires2' => $expirySql,
            ],
        );

        return $this->hits($bucket) <= $maxAttempts;
    }

    /**
     * Same as attempt(), but throws the 429 instead of returning false.
     *
     * @throws HttpException
     */
    public function enforce(string $key, int $maxAttempts, int $windowSeconds = 3600): void
    {
        if (!$this->attempt($key, $maxAttempts, $windowSeconds)) {
            throw HttpException::tooManyRequests($this->retryAfter($key));
        }
    }

    /** Read the counter without incrementing it. */
    public function hits(string $key): int
    {
        return $this->db->fetchInt(
            'SELECT hits FROM rate_limits WHERE bucket_key = :key AND expires_at > UTC_TIMESTAMP()',
            ['key' => $this->normaliseKey($key)],
        );
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->hits($key));
    }

    /** Seconds until the current window closes. */
    public function retryAfter(string $key): int
    {
        $seconds = $this->db->fetchValue(
            'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires_at)
             FROM rate_limits
             WHERE bucket_key = :key AND expires_at > UTC_TIMESTAMP()',
            ['key' => $this->normaliseKey($key)],
        );

        return max(1, (int) $seconds);
    }

    /** Clear a bucket - called after a successful login. */
    public function clear(string $key): void
    {
        $this->db->execute(
            'DELETE FROM rate_limits WHERE bucket_key = :key',
            ['key' => $this->normaliseKey($key)],
        );
    }

    /** Housekeeping for the cron worker. */
    public function pruneExpired(): int
    {
        return $this->db->execute('DELETE FROM rate_limits WHERE expires_at <= UTC_TIMESTAMP()');
    }

    /**
     * Keep the key inside VARCHAR(190) and free of anything odd.
     *
     * Long keys (an IPv6 address plus a long email) are hashed rather than
     * truncated, since truncation would merge distinct buckets and let one
     * attacker's limit apply to an unrelated user.
     */
    private function normaliseKey(string $key): string
    {
        $key = preg_replace('/\s+/', '', $key) ?? $key;

        return strlen($key) <= 190 ? $key : substr($key, 0, 150) . ':' . substr(hash('sha256', $key), 0, 32);
    }
}
