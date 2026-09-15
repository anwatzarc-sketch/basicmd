<?php

declare(strict_types=1);

namespace Aster\Presentation\Middleware;

use Aster\Domain\Exception\HttpException;
use Aster\Infrastructure\Security\Csrf;
use Aster\Infrastructure\Support\Logger;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;

/**
 * Rejects state-changing requests without a valid CSRF token.
 *
 * Applied to every non-GET route, public forms included. The booking and
 * contact forms are unauthenticated, but a forged cross-site POST against
 * them can still flood the appointment table with junk that a receptionist
 * then has to clear by hand.
 *
 * The token is read from the form field first, then the X-CSRF-Token header,
 * so both the plain form posts and the fetch()-driven availability calls are
 * covered by one rule.
 */
final readonly class VerifyCsrf
{
    public function __construct(
        private Csrf $csrf,
        private Logger $logger,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        // Safe methods do not mutate state, so they need no token.
        if (!$request->isWriteMethod()) {
            return $next($request);
        }

        $token = $request->input(Csrf::fieldName())
            ?? $request->header(Csrf::headerName());

        if (!$this->csrf->isValid($token)) {
            $this->logger->warning('CSRF token rejected', [
                'path'   => $request->path,
                'method' => $request->method,
                'ip'     => $request->ip(),
                // Whether a token was absent versus wrong distinguishes an
                // expired session from an actual forgery attempt.
                'reason' => $token === null ? 'missing' : 'mismatch',
            ]);

            throw HttpException::tokenMismatch();
        }

        return $next($request);
    }
}
