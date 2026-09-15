<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\EncounterException;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\UnsettledBalanceException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\Repository\WardLocationRepositoryInterface;
use Aster\Domain\Services\BillingService;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\QueueService;
use Aster\Domain\ValueObject\VisitNumber;
use Aster\Infrastructure\Persistence\PatientRepository;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * The unified encounter workbench (FRS 10.2) and the OPD-to-IPD upgrade
 * endpoint (FRS 10.3).
 *
 * Every state-changing action here is a thin pass-through into a Domain
 * service (EncounterService, BillingService, QueueService) - this
 * controller validates input and translates a domain exception into a
 * flash message, and nothing more. The business rules themselves - the
 * bed-locking discipline, the financial clearance gate, the walk-out
 * status selection - live entirely in Stage 1/3's Domain layer, already
 * verified there independently of any UI reaching it.
 */
final class EncounterController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly EncounterRepositoryInterface $encounters,
        private readonly WardLocationRepositoryInterface $wardLocations,
        private readonly LedgerRepositoryInterface $ledger,
        private readonly ReceivablePaymentRepositoryInterface $payments,
        private readonly PatientRepository $patients,
        private readonly UserRepository $users,
        private readonly EncounterService $encounterService,
        private readonly BillingService $billing,
        private readonly QueueService $queue,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function workbench(Request $request): Response
    {
        $visitValue = $request->input('visit');
        $encounter  = null;

        if ($visitValue !== null && $visitValue !== '') {
            $visitNumber = VisitNumber::tryFrom($visitValue);
            $encounter   = $visitNumber !== null ? $this->encounters->findByVisitNumber($visitNumber) : null;
        }

        if ($encounter !== null) {
            return $this->renderAdmin('admin/encounters/detail', [
                'encounter'         => $encounter,
                'patient'           => $this->patients->findById($encounter->patientId),
                'balance'           => $this->billing->calculateReceivableBalance($encounter->id),
                'cleared'           => $this->billing->verifyFinancialClearance($encounter->id),
                'ledgerEntries'     => $this->ledger->forEncounter($encounter->id),
                'paymentEntries'    => $this->payments->forEncounter($encounter->id),
                'availableBeds'     => $this->wardLocations->availableForAdmission(),
                'physicians'        => $this->users->all('physician', 'active'),
                'meta'              => ['title' => $encounter->patientVisitNumber->value, 'noindex' => true],
            ]);
        }

        return $this->renderAdmin('admin/encounters/workbench', [
            'searchTerm'   => $visitValue ?? '',
            'notFound'     => $visitValue !== null && $visitValue !== '',
            'active'       => $this->encounters->active(),
            'meta'         => ['title' => 'Encounter Workbench', 'noindex' => true],
        ]);
    }

    /** Start a walk-in encounter - no prior appointment (FRS 10.2). */
    public function startWalkIn(Request $request): Response
    {
        $patientId = $request->nullableInt('patient_id');
        $visitType = VisitType::tryFrom($request->string('visit_type'));
        $formPath  = $this->config->adminPath . '/encounters/workbench';

        if ($patientId === null || $visitType === null) {
            return $this->redirectWithError($formPath, 'A patient and a visit type are required to start an encounter.');
        }

        try {
            $encounter = $this->encounterService->startEncounter($patientId, $visitType, $request->nullableInt('location_id'));
        } catch (EncounterException $e) {
            return $this->redirectWithError($formPath, $e->getMessage());
        }

        return $this->redirectWithSuccess(
            $formPath . '?visit=' . urlencode($encounter->patientVisitNumber->value),
            sprintf('Encounter started: %s.', $encounter->patientVisitNumber->value),
        );
    }

    public function upgradeToIpd(Request $request): Response
    {
        $visitNumber = VisitNumber::tryFrom($request->string('patient_visit_number'));
        $bedId       = $request->nullableInt('target_bed_id');
        $physicianId = $request->nullableInt('physician_id');

        $back = $this->config->adminPath . '/encounters/workbench'
            . ($visitNumber !== null ? '?visit=' . urlencode($visitNumber->value) : '');

        if ($visitNumber === null || $bedId === null || $physicianId === null) {
            return $this->redirectWithError($back, 'A visit number, a target bed and a physician are all required.');
        }

        try {
            $this->encounterService->upgradeOpdToIpd($visitNumber, $bedId, $physicianId);
        } catch (EncounterException $e) {
            return $this->redirectWithError($back, $e->getMessage());
        }

        return $this->redirectWithSuccess($back, 'Patient admitted to inpatient care.');
    }

    public function discharge(Request $request): Response
    {
        $user      = $this->requireUser();
        $encounter = $this->encounters->findById($request->routeInt('id'));

        if ($encounter === null) {
            throw HttpException::notFound();
        }

        $back = $this->config->adminPath . '/encounters/workbench?visit=' . urlencode($encounter->patientVisitNumber->value);

        try {
            $this->billing->processDischargeOrClosure($encounter->id, $user->id);
        } catch (UnsettledBalanceException $e) {
            return $this->redirectWithError(
                $back,
                sprintf('Cannot discharge: outstanding balance of %s. Apply an override if this is authorised.', $e->balance->format()),
            );
        } catch (EncounterException $e) {
            return $this->redirectWithError($back, $e->getMessage());
        }

        return $this->redirectWithSuccess($back, 'Encounter discharged/closed.');
    }

    public function applyOverride(Request $request): Response
    {
        $user      = $this->requireUser();
        $encounter = $this->encounters->findById($request->routeInt('id'));

        if ($encounter === null) {
            throw HttpException::notFound();
        }

        $back   = $this->config->adminPath . '/encounters/workbench?visit=' . urlencode($encounter->patientVisitNumber->value);
        $reason = $request->string('reason');

        if (trim($reason) === '') {
            return $this->redirectWithError($back, 'A reason is required to apply a financial clearance override.');
        }

        try {
            $this->billing->applyOverride($encounter->id, $user->id, $reason);
        } catch (EncounterException $e) {
            return $this->redirectWithError($back, $e->getMessage());
        }

        return $this->redirectWithSuccess($back, 'Financial clearance overridden. The encounter may now be discharged.');
    }

    public function walkOut(Request $request): Response
    {
        $encounter = $this->encounters->findById($request->routeInt('id'));

        if ($encounter === null) {
            throw HttpException::notFound();
        }

        $back   = $this->config->adminPath . '/encounters/workbench';
        $reason = $request->string('reason');

        try {
            $this->queue->markWalkOut($encounter->id, $reason);
        } catch (EncounterException $e) {
            return $this->redirectWithError($back . '?visit=' . urlencode($encounter->patientVisitNumber->value), $e->getMessage());
        }

        return $this->redirectWithSuccess($back, 'Recorded as a walk-out. Existing charges remain on the account.');
    }
}
