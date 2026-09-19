<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Middleware;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Translator;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;

/**
 * Resolves the display language for the request.
 *
 * Precedence: explicit ?lang= choice, then the saved cookie, then the site
 * default. The Accept-Language header is deliberately ignored - most browsers
 * in Ethiopia report en-US regardless of what the person actually reads, so
 * honouring it would hide the Amharic site from the people who want it.
 *
 * An explicit choice is persisted for a year so a returning patient does not
 * have to switch on every visit.
 */
final readonly class SetLocale
{
    private const string COOKIE_NAME = 'medicaremini_locale';

    public function __construct(
        private Translator $translator,
        private Config $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $locale     = $request->preferredLocale($this->config->defaultLocale);
        $isExplicit = $request->has('lang');

        $this->translator->setLocale($locale);

        // Shared so views, emails and JSON responses all agree.
        $GLOBALS['medicaremini_locale'] = $locale;

        $response = $next($request);

        if ($isExplicit) {
            $this->persist($locale, $request->isSecure());
        }

        // Tells caches that the same URL renders differently per cookie.
        return $response->withHeader('Vary', 'Cookie');
    }

    private function persist(Locale $locale, bool $secure): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $locale->value, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => $secure,
            // Readable by JavaScript is fine and useful; this is a display
            // preference, not a credential.
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}
