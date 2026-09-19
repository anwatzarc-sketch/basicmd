<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Enum\Locale;

/**
 * Runtime configuration from the system_settings table.
 *
 * Read once per request and cached in memory: the public layout alone touches
 * a dozen settings, and they must not cost a dozen round trips.
 *
 * Secrets never live here - .env holds those. Everything in this table is
 * either public or merely operational, which is what makes it safe to expose
 * through the settings screen.
 */
final class SettingsRepository
{
    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, string|null> */
    private function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->db->fetchPairs('SELECT setting_key, value FROM system_settings');
        }

        return $this->cache;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function string(string $key, string $default = ''): string
    {
        return $this->get($key) ?? $default;
    }

    /**
     * A setting in the requested language, falling back to the base row.
     *
     * Translated settings are stored as sibling keys - `address`, `address_am`,
     * `address_om` - so the suffix comes from the locale itself rather than
     * from a ternary at each call site. Every caller previously hard-coded
     * "is this Amharic?", which silently shut a third language out of the
     * clinic name, the tagline, the address and the opening hours.
     *
     * A blank translation falls back too: an empty row is a translation that
     * was never filled in, not an instruction to render nothing.
     */
    public function localized(string $key, Locale $locale, string $default = ''): string
    {
        $base = $this->string($key, $default);

        if ($locale === Locale::EN) {
            return $base;
        }

        $translated = $this->string($key . $locale->columnSuffix());

        return $translated !== '' ? $translated : $base;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return $value === null || !is_numeric($value) ? $default : (float) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<mixed> */
    public function json(string $key, array $default = []): array
    {
        $value = $this->get($key);

        if ($value === null || !json_validate($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Settings safe to hand to a public template.
     *
     * @return array<string, string|null>
     */
    public function publicSettings(): array
    {
        return $this->db->fetchPairs(
            'SELECT setting_key, value FROM system_settings WHERE is_public = 1'
        );
    }

    /**
     * All rows in a group, with their metadata, for the settings screen.
     *
     * @return list<array<string, mixed>>
     */
    public function group(string $group): array
    {
        return $this->db->fetchAll(
            'SELECT setting_key, value, value_type, label, group_name, is_public
             FROM system_settings
             WHERE group_name = :g
             ORDER BY setting_key',
            ['g' => $group],
        );
    }

    /** @return list<string> */
    public function groups(): array
    {
        return array_column(
            $this->db->fetchAll('SELECT DISTINCT group_name FROM system_settings ORDER BY group_name'),
            'group_name',
        );
    }

    /**
     * Update several settings at once.
     *
     * Only keys that already exist are written. A settings form cannot
     * therefore be used to inject arbitrary new keys by adding fields to the
     * POST body.
     *
     * @param array<string, string|null> $values
     */
    public function saveMany(array $values, ?int $userId = null): int
    {
        $updated = 0;

        $this->db->transaction(function (Database $db) use ($values, $userId, &$updated): void {
            foreach ($values as $key => $value) {
                $updated += $db->execute(
                    'UPDATE system_settings
                     SET value = :v, updated_by = :u
                     WHERE setting_key = :k',
                    ['v' => $value, 'u' => $userId, 'k' => $key],
                );
            }
        });

        $this->cache = null;

        return $updated;
    }

    public function set(string $key, ?string $value, ?int $userId = null): bool
    {
        $affected = $this->db->execute(
            'UPDATE system_settings SET value = :v, updated_by = :u WHERE setting_key = :k',
            ['v' => $value, 'u' => $userId, 'k' => $key],
        );

        $this->cache = null;

        return $affected > 0;
    }

    /** Drop the in-memory cache, e.g. in a long-running worker. */
    public function refresh(): void
    {
        $this->cache = null;
    }
}
