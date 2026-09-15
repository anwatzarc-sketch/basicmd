<?php

declare(strict_types=1);

namespace Aster\Presentation\Middleware;

use Aster\Application\Service\PatientAuthService;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;

/**
 * Requires a signed-in patient (FRS 11.1: "Patient portal routes must
 * require an authenticated patient account").
 *
 * Deliberately the mirror of Authenticate, not a variant of it: it
 * validates and writes to the session's PATIENT identity slot only
 * (SessionManager::validatePatientSession()/loginPatient()/patientId()),
 * and caches the resolved Patient onto $GLOBALS['aster_current_patient'] -
 * a different global from staff's aster_current_user. Authorize's `can:`
 * checks read aster_current_user exclusively, so a patient session can
 * never satisfy one; this middleware never touches that global at all,
 * which is what makes that true by construction rather than by a check
 * someone has to remember to add.
 */
final readonly class AuthenticatePatient
{
    public function __construct(
        private PatientAuthService $auth,
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

        if (!$this->session->validatePatientSession($fingerprint)) {
            return $this->redirectToLogin($request, 'Your session expired. Please sign in again.');
        }

        $patient = $this->auth->currentPatient();

        if ($patient === null) {
            return $this->redirectToLogin($request);
        }

        $GLOBALS['aster_current_patient'] = $patient;

        return $next($request)->withoutCache();
    }

    private function redirectToLogin(Request $request, ?string $message = null): Response
    {
        if ($message !== null) {
            $this->session->flash('warning', $message);
        }

        $target = $request->isGet() ? '?return=' . rawurlencode($request->fullUri()) : '';

        return Response::redirect($this->config->url('patient/portal/login') . $target);
    }
}
