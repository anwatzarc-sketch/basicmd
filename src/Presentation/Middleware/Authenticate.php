<?php

declare(strict_types=1);

namespace Aster\Presentation\Middleware;

use Aster\Application\Service\AuthService;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;

/**
 * Requires a signed-in staff member.
 *
 * On failure it redirects to the login page rather than throwing a 401,
 * carrying the attempted path in ?return= so the user lands back where they
 * were trying to go. Request::safeRedirectTarget() validates that value on
 * the way back out, so the parameter cannot become an open redirect.
 *
 * Also enforces the session timeouts and the must-change-password gate.
 */
final readonly class Authenticate
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $session,
        private Config $config,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $fingerprint = SessionManager::fingerprint(
            $request->userAgent(),
            $request->ip($this->config->trustProxy()),
        );

        // Idle timeout, absolute cap, and the stolen-cookie check.
        if (!$this->session->validate($fingerprint)) {
            return $this->redirectToLogin($request, 'Your session expired. Please sign in again.');
        }

        $user = $this->auth->currentUser();

        if ($user === null) {
            return $this->redirectToLogin($request);
        }

        // A temporary password gets exactly one use: reaching any page other
        // than the change-password screen bounces back to it.
        if ($user->mustChangePassword && !str_contains($request->path, 'password')) {
            $this->session->flash('warning', 'Please set a new password before continuing.');

            return Response::redirect($this->config->adminUrl('password'));
        }

        // Cached on the request so controllers and views need not re-query.
        $GLOBALS['aster_current_user'] = $user;

        return $next($request)->withoutCache();
    }

    private function redirectToLogin(Request $request, ?string $message = null): Response
    {
        if ($message !== null) {
            $this->session->flash('warning', $message);
        }

        // Only GET paths are worth returning to; re-posting a form after
        // login would replay a half-finished action.
        $target = $request->isGet() ? '?return=' . rawurlencode($request->fullUri()) : '';

        return Response::redirect($this->config->adminUrl('login') . $target);
    }
}
