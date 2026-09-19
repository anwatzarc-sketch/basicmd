<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Http;

use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Logger;
use MediCareMini\Presentation\View\View;
use Throwable;

/**
 * Centralised exception handling.
 *
 * The rule: the visitor gets a friendly page and nothing else. Stack traces,
 * SQL, file paths and bound parameters all go to the log file only. On a
 * medical site a leaked query can expose a patient name or phone number, so
 * the debug view is additionally hard-disabled whenever APP_ENV=production,
 * regardless of what APP_DEBUG says.
 */
final readonly class ErrorHandler
{
    public function __construct(
        private View $view,
        private Logger $logger,
        private Config $config,
    ) {
    }

    public function handle(Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->statusCode : 500;

        $this->log($e, $request, $status);

        if ($request->wantsJson()) {
            return $this->jsonResponse($e, $status);
        }

        return $this->htmlResponse($e, $status, $request);
    }

    private function log(Throwable $e, Request $request, int $status): void
    {
        $context = [
            'status' => $status,
            'method' => $request->method,
            'path'   => $request->path,
            'ip'     => $request->ip($this->config->trustProxy()),
        ];

        // Expected client-side conditions are noise at error level; a 404 or
        // a stale CSRF token is not an incident.
        if ($status < 500) {
            $this->logger->info($e->getMessage(), $context);

            return;
        }

        $this->logger->exception($e, 'error', $context);
    }

    private function jsonResponse(Throwable $e, int $status): Response
    {
        $payload = [
            'ok'      => false,
            'message' => $status < 500
                ? $e->getMessage()
                : 'Something went wrong. Please try again.',
        ];

        if ($this->config->appDebug) {
            $payload['debug'] = [
                'exception' => $e::class,
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        $response = Response::json($payload, $status);

        if ($e instanceof HttpException) {
            $response = $response->withHeaders($e->headers);
        }

        return $response;
    }

    private function htmlResponse(Throwable $e, int $status, Request $request): Response
    {
        // Admin errors keep the admin chrome so a signed-in user is not
        // dumped onto the public site mid-task.
        $isAdmin = str_starts_with(ltrim($request->path, '/'), $this->config->adminPath);

        try {
            // The layouts and partials expect these on every render. Controller
            //::render() normally shares them, but an exception can be thrown
            // before any controller ran - a 404 has no controller at all - so
            // the error path has to supply them itself or the error page
            // fatals while reporting the original error.
            $this->view->share([
                'view'     => $this->view,
                'locale'   => $this->view->translator->locale(),
                'flash'    => [],
                'old'      => [],
                'errors'   => [],
                'user'     => $GLOBALS['medicaremini_current_user'] ?? null,
                'settings' => null,
                'cspNonce' => $GLOBALS['medicaremini_csp_nonce'] ?? '',
            ]);

            $html = $this->view->renderWithLayout(
                'errors/error',
                $isAdmin ? 'layouts/auth' : 'layouts/public',
                [
                    'status'  => $status,
                    'title'   => $this->view->translator->get("errors.{$status}_title"),
                    'message' => $this->messageFor($e, $status),
                    'debug'   => $this->config->appDebug ? $this->debugPayload($e) : null,
                    'meta'    => [
                        'title'   => $this->view->translator->get("errors.{$status}_title"),
                        'noindex' => true,
                    ],
                ],
            );
        } catch (Throwable $renderFailure) {
            // The error page itself failed. Fall back to inline HTML rather
            // than recursing into the handler.
            $this->logger->critical('Error page failed to render', [
                'original' => $e->getMessage(),
                'render'   => $renderFailure->getMessage(),
            ]);

            $html = $this->minimalPage($status);
        }

        $response = Response::html($html, $status);

        if ($e instanceof HttpException) {
            $response = $response->withHeaders($e->headers);
        }

        return $response;
    }

    /**
     * The message shown to the visitor.
     *
     * 4xx messages come from HttpException, which only ever carries text
     * written to be safe for display. Everything else gets a generic string -
     * a raw PDOException message would name tables and columns.
     */
    private function messageFor(Throwable $e, int $status): string
    {
        if ($e instanceof HttpException && $status < 500) {
            return $e->getMessage();
        }

        $key = "errors.{$status}_body";
        $translated = $this->view->translator->get($key);

        return $translated === $key ? $this->view->translator->get('errors.500_body') : $translated;
    }

    /** @return array<string, mixed> */
    private function debugPayload(Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => explode("\n", $e->getTraceAsString()),
        ];
    }

    /** Last-resort page with no template dependency. */
    private function minimalPage(int $status): string
    {
        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error %1$d</title></head>'
            . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;'
            . 'min-height:100vh;margin:0;background:#f8fafc;color:#0f172a;text-align:center;padding:24px;">'
            . '<div><h1 style="font-size:48px;margin:0;color:#056460;">%1$d</h1>'
            . '<p style="color:#64748b;">Something went wrong. Please try again shortly.</p>'
            . '<a href="/" style="color:#056460;font-weight:600;">Return to homepage</a></div>'
            . '</body></html>',
            $status,
        );
    }

    /**
     * Severities that are reported but never thrown.
     *
     * A deprecation says a construct will break in a FUTURE PHP release; it
     * says nothing about whether the current request is correct. Throwing on
     * one means any notice raised by PHP itself or by a vendor package takes
     * the whole site down on an upgrade, which is a far worse outcome than the
     * warning it was meant to surface. They are logged instead, so the signal
     * survives without being fatal.
     *
     * @var list<int>
     */
    private const array NON_FATAL_SEVERITIES = [E_DEPRECATED, E_USER_DEPRECATED];

    /**
     * Register global handlers.
     *
     * Converts PHP notices and warnings into exceptions so they cannot
     * silently corrupt a booking, logs deprecations without throwing, and
     * catches fatals that bypass the normal try/catch path.
     */
    public function register(): void
    {
        $logger = $this->logger;

        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
            // Respect any @-suppression and the configured error_reporting.
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            if (in_array($severity, self::NON_FATAL_SEVERITIES, true)) {
                // A deprecation inside a loop would otherwise write the same
                // line thousands of times and bury everything else in the log.
                static $seen = [];

                $key = $file . ':' . $line . ':' . $message;

                if (!isset($seen[$key])) {
                    $seen[$key] = true;

                    // warning, not notice: the shipped LOG_LEVEL is `warning`,
                    // so a notice here would be dropped and the deprecation
                    // would vanish entirely rather than merely stop being fatal.
                    $logger->warning('Deprecated: ' . $message, ['file' => $file . ':' . $line]);
                }

                // Handled - returning true stops PHP printing it into the page.
                return true;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();

            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $this->logger->critical('Fatal error', [
                'message' => $error['message'],
                'file'    => $error['file'] . ':' . $error['line'],
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
                echo $this->minimalPage(500);
            }
        });
    }
}
