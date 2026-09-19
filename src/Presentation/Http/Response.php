<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Http;

/**
 * An HTTP response, assembled in memory and sent once.
 *
 * Controllers return one of these rather than echoing, so middleware can
 * still add headers after the controller has finished and nothing is
 * committed until send() runs.
 */
final class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array|object $data, int $status = 200): self
    {
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return new self($encoded, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function xml(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Stream a file from disk.
     *
     * Used for payment proofs, which live outside the webroot and must only
     * ever reach an authenticated staff member. Content-Disposition defaults
     * to `inline` so a slip previews in the browser; the nosniff header stops
     * a crafted upload being reinterpreted as active content.
     */
    public static function file(
        string $absolutePath,
        string $mimeType,
        string $downloadName,
        bool $forceDownload = false,
    ): self {
        $disposition = $forceDownload ? 'attachment' : 'inline';

        // RFC 5987 encoding keeps non-ASCII filenames intact.
        $fallbackName = preg_replace('/[^\x20-\x7E]/', '_', $downloadName) ?? 'file';

        $response = new self('', 200, [
            'Content-Type'           => $mimeType,
            'Content-Disposition'    => sprintf(
                "%s; filename=\"%s\"; filename*=UTF-8''%s",
                $disposition,
                str_replace('"', '', $fallbackName),
                rawurlencode($downloadName),
            ),
            'Content-Length'         => (string) (filesize($absolutePath) ?: 0),
            'X-Content-Type-Options' => 'nosniff',
            // Patient financial documents must never be cached by a proxy.
            'Cache-Control'          => 'private, no-store, max-age=0',
        ]);

        $response->body = (string) file_get_contents($absolutePath);

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** Mark a response as uncacheable - used for every authenticated page. */
    public function withoutCache(): self
    {
        return $this->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /** Cache a public asset or page for a fixed number of seconds. */
    public function withCache(int $seconds): self
    {
        return $this->withHeader('Cache-Control', "public, max-age={$seconds}");
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        // 204 and 304 must not carry a body.
        if ($this->status !== 204 && $this->status !== 304) {
            echo $this->body;
        }
    }
}
