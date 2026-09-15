<?php

declare(strict_types=1);

namespace Aster\Presentation\Http;

use Aster\Domain\Enum\Locale;

/**
 * Immutable snapshot of the incoming HTTP request.
 *
 * Built once in the front controller from the superglobals, which are then
 * never read again anywhere in the application. That single choke point is
 * what makes input handling auditable: there is exactly one place where
 * untrusted data enters.
 */
final class Request
{
    /** @param array<string, mixed> $attributes route params merged by the router */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly array $cookies,
        public readonly array $server,
        private array $attributes = [],
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Browsers can only send GET and POST from a form. A _method field on
        // a POST lets templates express PUT/PATCH/DELETE semantics; it is only
        // honoured on POST, so a GET link can never trigger a destructive verb.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            method:   $method,
            path:     '/' . trim(is_string($path) ? rawurldecode($path) : '/', '/'),
            query:    $_GET,
            body:     $_POST,
            files:    $_FILES,
            cookies:  $_COOKIE,
            server:   $_SERVER,
        );
    }

    // --- Input accessors ------------------------------------------------

    /**
     * A single scalar input value, trimmed. Arrays return null, so a caller
     * expecting a string can never be handed one by a crafted query like
     * `?name[]=a`.
     */
    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;

        if (is_array($value) || $value === null) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    /** Required-string accessor that never returns null. */
    public function string(string $key, string $default = ''): string
    {
        return $this->input($key) ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || !is_numeric($value) ? null : (int) $value;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        return $value === null || !is_numeric($value) ? $default : (float) $value;
    }

    /** Checkbox semantics: present and truthy. */
    public function bool(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * A list of scalar values, e.g. multi-select. Non-scalar members are
     * dropped rather than passed through.
     *
     * @return list<string>
     */
    public function array(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '',
            array_filter($value, static fn (mixed $v): bool => is_scalar($v)),
        ));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    // --- Request metadata -----------------------------------------------

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isWriteMethod(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    /** True when the client asked for JSON rather than HTML. */
    public function wantsJson(): bool
    {
        return $this->isAjax()
            || str_contains(strtolower($this->header('Accept') ?? ''), 'application/json');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        $value = $this->server[$key]
            ?? $this->server[strtoupper(str_replace('-', '_', $name))]
            ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Client IP.
     *
     * X-Forwarded-For is only consulted when TRUST_PROXY is on, because any
     * client can set that header. Trusting it unconditionally would let an
     * attacker forge the address recorded in audit_logs and sidestep every
     * IP-keyed rate limit.
     */
    public function ip(bool $trustProxy = false): string
    {
        if ($trustProxy) {
            $forwarded = $this->header('X-Forwarded-For');

            if ($forwarded !== null && $forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);

                if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return $first;
                }
            }
        }

        $remote = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        return is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false
            ? $remote
            : '0.0.0.0';
    }

    /** Packed binary form for the VARBINARY(16) audit columns. */
    public function ipBinary(bool $trustProxy = false): ?string
    {
        $packed = @inet_pton($this->ip($trustProxy));

        return $packed === false ? null : $packed;
    }

    public function userAgent(): string
    {
        $ua = $this->server['HTTP_USER_AGENT'] ?? '';

        return is_string($ua) ? mb_substr($ua, 0, 255) : '';
    }

    public function host(): string
    {
        $host = $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? '';

        return is_string($host) ? strtolower(explode(':', $host)[0]) : '';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return strtolower($this->header('X-Forwarded-Proto') ?? '') === 'https';
    }

    /** Full request URI including the query string. */
    public function fullUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? $this->path;

        return is_string($uri) ? $uri : $this->path;
    }

    /**
     * Where a redirect should return the user after login. Only same-origin
     * relative paths are accepted, which closes the open-redirect hole.
     */
    public function safeRedirectTarget(string $key = 'return', string $fallback = '/'): string
    {
        $target = $this->input($key);

        if ($target === null
            || !str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_contains($target, "\\")
        ) {
            return $fallback;
        }

        return $target;
    }

    public function preferredLocale(Locale $default = Locale::EN): Locale
    {
        // Explicit choice wins, then the persisted cookie, then the default.
        if ($this->has('lang')) {
            return Locale::fromRequest($this->input('lang'), $default);
        }

        $cookie = $this->cookies['aster_locale'] ?? null;

        return Locale::fromRequest(is_string($cookie) ? $cookie : null, $default);
    }

    // --- Router attributes ----------------------------------------------

    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = $attributes + $this->attributes;

        return $clone;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function routeInt(string $key, int $default = 0): int
    {
        $value = $this->attributes[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function routeString(string $key, string $default = ''): string
    {
        $value = $this->attributes[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
