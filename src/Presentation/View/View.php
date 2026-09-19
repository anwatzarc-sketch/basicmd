<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\View;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\ValueObject\CompanyBrand;
use MediCareMini\Infrastructure\Security\Csrf;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Translator;
use RuntimeException;
use Throwable;

/**
 * Plain-PHP template renderer.
 *
 * No template-engine dependency: PHP is already a template language, OPcache
 * compiles these files once, and there is no cache directory to make writable
 * on a shared host.
 *
 * The safety rule this class enforces is escaping. `$this->e()` is the only
 * way values reach the page in templates, and the layout system never echoes
 * a variable directly. Anything that must emit raw HTML - article bodies from
 * the CMS - goes through `raw()`, which is deliberately conspicuous and
 * sanitises rather than trusting.
 */
final class View
{
    /** @var array<string, mixed> data shared with every template */
    private array $shared = [];

    /** @var array<string, string> captured named sections */
    private array $sections = [];

    private ?string $currentSection = null;

    public function __construct(
        private readonly string $viewPath,
        public readonly Translator $translator,
        public readonly Config $config,
        public readonly CompanyBrand $brand,
        private readonly ?Csrf $csrf = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function share(array $data): void
    {
        $this->shared = $data + $this->shared;
    }

    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->resolve($template);

        // extract() is confined to this method: templates receive exactly the
        // variables passed plus the shared set, and nothing from the caller's
        // scope leaks in.
        $variables = $data + $this->shared;

        $level = ob_get_level();
        ob_start();

        try {
            (function () use ($file, $variables): void {
                extract($variables, EXTR_SKIP);
                require $file;
            })();

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            // Discard partial output; a half-rendered page must never reach
            // the browser alongside an error handler's output.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    /**
     * Render a template inside a layout.
     *
     * The template runs first and captures its sections, then the layout
     * renders and pulls them out with `section()`.
     *
     * @param array<string, mixed> $data
     */
    public function renderWithLayout(string $template, string $layout, array $data = []): string
    {
        $content = $this->render($template, $data);

        return $this->render($layout, $data + ['content' => $content]);
    }

    private function resolve(string $template): string
    {
        $relative = str_replace(['..', "\0"], '', $template);
        $file     = $this->viewPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$template}");
        }

        return $file;
    }

    // -----------------------------------------------------------------
    //  Escaping - the core safety primitive
    // -----------------------------------------------------------------

    /**
     * Escape for HTML text and quoted attribute contexts.
     *
     * ENT_QUOTES handles both quote styles; ENT_SUBSTITUTE replaces invalid
     * UTF-8 with U+FFFD instead of returning an empty string, which is what
     * turns a malformed byte in a patient's name into a silently blank field.
     */
    public function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Translate and escape - the common case in templates. */
    public function t(string $key, array $replacements = []): string
    {
        return $this->e($this->translator->get($key, $replacements));
    }

    /** Translated string without escaping, for building attribute values. */
    public function tRaw(string $key, array $replacements = []): string
    {
        return $this->translator->get($key, $replacements);
    }

    /**
     * Emit stored HTML (article bodies, formatted instructions).
     *
     * CMS content is authored by trusted staff, but "trusted" is not
     * "unconditionally safe": an account can be compromised, and a pasted
     * block from an outside document can carry a script tag. Everything is
     * filtered to a known-good tag and attribute set.
     */
    public function raw(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return HtmlSanitiser::clean($html);
    }

    /** JSON for an inline data attribute or script block. */
    public function json(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );

        return $encoded === false ? '{}' : $encoded;
    }

    /** Escaped attribute value for a URL, rejecting javascript: schemes. */
    public function url(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path) === 1) {
            return $this->e($path);
        }

        return $this->e($this->config->url($path));
    }

    public function adminUrl(string $path = ''): string
    {
        return $this->e($this->config->adminUrl($path));
    }

    /**
     * Sanitise an outbound href.
     *
     * Blocks javascript:, data: and vbscript: URLs, which is what turns a
     * stored social-media link from the settings screen into an XSS vector.
     */
    public function href(?string $url, string $fallback = '#'): string
    {
        if ($url === null || trim($url) === '') {
            return $fallback;
        }

        $url     = trim($url);
        $lowered = strtolower($url);

        foreach (['javascript:', 'data:', 'vbscript:', 'file:'] as $scheme) {
            if (str_starts_with(str_replace([' ', "\t", "\n"], '', $lowered), $scheme)) {
                return $fallback;
            }
        }

        return $this->e($url);
    }

    // -----------------------------------------------------------------
    //  Sections
    // -----------------------------------------------------------------

    public function start(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new RuntimeException("Cannot nest section '{$name}' inside '{$this->currentSection}'.");
        }

        $this->currentSection = $name;
        ob_start();
    }

    public function stop(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('stop() called with no open section.');
        }

        $this->sections[$this->currentSection] = (string) ob_get_clean();
        $this->currentSection = null;
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** Render a partial inline. @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    // -----------------------------------------------------------------
    //  Helpers used throughout the templates
    // -----------------------------------------------------------------

    public function csrfField(): string
    {
        return $this->csrf?->field() ?? '';
    }

    public function csrfToken(): string
    {
        return $this->csrf?->token() ?? '';
    }

    public function locale(): Locale
    {
        return $this->translator->locale();
    }

    public function isAmharic(): bool
    {
        return $this->translator->isAmharic();
    }

    /** `checked`/`selected`/`disabled` attribute when the condition holds. */
    public function attr(bool $condition, string $attribute): string
    {
        return $condition ? ' ' . $attribute : '';
    }

    /** Join conditional CSS classes. @param array<string, bool>|list<string> $classes */
    public function classes(array $classes): string
    {
        $out = [];

        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $out[] = $value;
                }
            } elseif ($value) {
                $out[] = $key;
            }
        }

        return $this->e(implode(' ', $out));
    }

    /**
     * Cache-busting asset URL.
     *
     * The file's mtime is appended so a redeploy invalidates the browser
     * cache without a build step that rewrites filenames.
     */
    public function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $file     = $this->config->path('public/' . $relative);
        $version  = is_file($file) ? (string) filemtime($file) : '1';

        return $this->e('/' . $relative . '?v=' . $version);
    }

    /** Public URL for an uploaded media file. */
    public function media(?string $relativePath): ?string
    {
        return $relativePath === null || $relativePath === ''
            ? null
            : $this->e('/media/' . ltrim($relativePath, '/'));
    }

    /** Truncate on a word boundary, appending an ellipsis. */
    public function excerpt(?string $text, int $length = 150): string
    {
        if ($text === null) {
            return '';
        }

        $flat = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($flat) <= $length) {
            return $this->e($flat);
        }

        $cut   = mb_substr($flat, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return $this->e(($space !== false ? mb_substr($cut, 0, $space) : $cut) . "\u{2026}");
    }
}
