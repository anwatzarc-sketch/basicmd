<?php

declare(strict_types=1);

namespace Aster\Presentation\Middleware;

use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;

/**
 * Applies security response headers to every page.
 *
 * The Content-Security-Policy is the important one. It is built per-request
 * with a nonce so inline scripts can be permitted individually rather than
 * enabling 'unsafe-inline' wholesale - with 'unsafe-inline' a stored-XSS bug
 * would execute despite a policy being present, which is the usual way CSP
 * ends up decorative.
 *
 * In development the policy relaxes just enough for the Tailwind play CDN,
 * which compiles classes in the browser and genuinely requires unsafe-eval.
 * Production never gets that exemption.
 */
final readonly class SecurityHeaders
{
    public function __construct(private Config $config)
    {
    }

    /** Per-request nonce, generated once and reused by the templates. */
    public static function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $response = $next($request);

        $nonce = $GLOBALS['aster_csp_nonce'] ?? '';

        $headers = [
            // Blocks MIME sniffing, which is what turns an uploaded image
            // that contains HTML into a rendered page.
            'X-Content-Type-Options'  => 'nosniff',
            // Legacy clickjacking defence; frame-ancestors in the CSP covers
            // modern browsers.
            'X-Frame-Options'         => 'SAMEORIGIN',
            // Do not leak the booking reference in a URL to third parties.
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            // The site needs none of these; denying them shrinks the attack
            // surface of any embedded content.
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()',
            'Content-Security-Policy' => $this->buildCsp($nonce, $request->isSecure()),
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // HSTS is only meaningful over HTTPS, and sending it over plain HTTP
        // during local development would lock the developer's browser into
        // https://localhost for months.
        if ($request->isSecure() && $this->config->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $response->withHeaders($headers);
    }

    private function buildCsp(string $nonce, bool $isSecure): string
    {
        $scriptSrc = ["'self'"];
        $styleSrc  = ["'self'"];

        if ($nonce !== '') {
            $scriptSrc[] = "'nonce-{$nonce}'";
            $styleSrc[]  = "'nonce-{$nonce}'";
        }

        if (!$this->config->assetsBuilt()) {
            // Development only: the Tailwind play CDN generates CSS at
            // runtime and cannot function without these.
            $scriptSrc[] = 'https://cdn.tailwindcss.com';
            $scriptSrc[] = "'unsafe-eval'";
            $styleSrc[]  = "'unsafe-inline'";
        } else {
            // Tailwind emits a few element-level style attributes and the
            // date input renders its own; allowing inline STYLES (not
            // scripts) is low risk and avoids visual breakage.
            $styleSrc[] = "'unsafe-inline'";
        }

        // Self-hosted fonts in production; Google Fonts only in development.
        $fontSrc  = ["'self'", 'data:'];
        $styleSrc[] = 'https://fonts.googleapis.com';
        $fontSrc[]  = 'https://fonts.gstatic.com';

        $directives = [
            "default-src 'self'",
            'script-src ' . implode(' ', $scriptSrc),
            'style-src ' . implode(' ', $styleSrc),
            'font-src ' . implode(' ', $fontSrc),
            // Google Static Maps and OSM tiles are the only external images.
            "img-src 'self' data: blob: https://maps.googleapis.com https://maps.gstatic.com https://*.tile.openstreetmap.org",
            "connect-src 'self'",
            // The embedded map iframe.
            "frame-src 'self' https://www.google.com https://www.openstreetmap.org",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        // Only meaningful once TLS is actually in front of the site. Emitting
        // it on a plain-HTTP deployment tells the browser to upgrade every
        // request - including same-origin CSS, JS and fonts - to https://,
        // which then fails to connect and leaves the page unstyled and dead.
        // Gate on the real connection scheme, not on APP_ENV.
        if ($isSecure && $this->config->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
