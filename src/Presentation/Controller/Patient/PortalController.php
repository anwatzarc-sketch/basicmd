<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Controller\Patient;

use MediCareMini\Application\Service\PatientAuthService;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Domain\Exception\ValidationException;
use MediCareMini\Domain\Repository\DiagnosticOrderRepositoryInterface;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Services\BillingService;
use MediCareMini\Infrastructure\Security\SessionManager;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Presentation\Controller\Controller;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;
use MediCareMini\Presentation\View\View;

/**
 * Patient self-service portal (FRS 10.6).
 *
 * Every read here is scoped to the signed-in patient's OWN id, taken from
 * the session (requirePatient()) rather than from any request input - the
 * FRS's own security requirement ("Patient users must not access another
 * patient's encounters, notes, diagnostics, prescriptions, or balances",
 * 11.1) is enforced by construction: there is no patient_id parameter
 * anywhere on this controller for a crafted request to override.
 */
final class PortalController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly PatientAuthService $auth,
        private readonly EncounterRepositoryInterface $encounters,
        private readonly DiagnosticOrderRepositoryInterface $diagnostics,
        private readonly BillingService $billing,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function loginForm(Request $request): Response
    {
        if ($this->auth->currentPatient() !== null) {
            return $this->redirect($this->config->url('patient/portal/dashboard'));
        }

        return $this->render('patient/portal/login', [
            'return' => $request->safeRedirectTarget('return', ''),
            'meta'   => ['title' => 'Patient Portal Sign In', 'noindex' => true],
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        try {
            $this->auth->login(
                pid:       $request->string('pid'),
                password:  $request->string('password'),
                ip:        $request->ip($this->config->trustProxy()),
                ipBinary:  $request->ipBinary($this->config->trustProxy()),
                userAgent: $request->userAgent(),
            );
        } catch (ValidationException $e) {
            $this->session->flashErrors($e->flatErrors());
            $this->session->flashInput(['pid' => $request->string('pid')]);
            $this->session->flash('error', $e->getMessage());

            return $this->redirect($this->config->url('patient/portal/login'));
        } catch (HttpException $e) {
            $this->session->flash('error', $e->getMessage());

            return $this->redirect($this->config->url('patient/portal/login'));
        }

        $return = $request->safeRedirectTarget('return', '');

        return $return !== '' && $return !== '/'
            ? Response::redirect($return)
            : $this->redirect($this->config->url('patient/portal/dashboard'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout(
            $this->currentPatient(),
            $request->ipBinary($this->config->trustProxy()),
            $request->userAgent(),
        );

        $this->session->start();
        $this->session->flash('success', 'You have been signed out.');

        return $this->redirect($this->config->url('patient/portal/login'));
    }

    public function dashboard(Request $request): Response
    {
        $patient = $this->requirePatient();

        $encounters = $this->encounters->forPatient($patient->id);

        $balances = [];

        foreach ($encounters as $encounter) {
            $balance = $this->billing->calculateReceivableBalance($encounter->id);

            if ($balance > 0.0) {
                $balances[] = ['encounter' => $encounter, 'balance' => $balance];
            }
        }

        return $this->renderPortal('patient/portal/dashboard', [
            'patient'     => $patient,
            'encounters'  => $encounters,
            'diagnostics' => $this->diagnostics->forPatient($patient->id),
            'balances'    => $balances,
            'meta'        => ['title' => 'My Portal', 'noindex' => true],
        ]);
    }
}
