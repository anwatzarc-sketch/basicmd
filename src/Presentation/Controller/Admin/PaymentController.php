<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\Service\PaymentService;
use Aster\Domain\Enum\PaymentChannel;
use Aster\Domain\Enum\ProofStatus;
use Aster\Domain\Exception\BookingException;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\PaymentRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Finance: proof-of-payment verification and transfer-method management.
 *
 * This is the money path, so two things are treated as non-negotiable:
 * proof files are streamed only through an authenticated action that logs
 * each view, and a rejection always carries a reason the patient can act on.
 */
final class PaymentController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly PaymentRepository $payments,
        private readonly AppointmentRepository $appointments,
        private readonly PaymentService $paymentService,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        // Default to the review queue: an officer opening this page is
        // almost always here to clear pending slips.
        $filters = [
            'status'    => $request->input('status') ?? ProofStatus::SUBMITTED->value,
            'search'    => $request->input('q'),
            'method_id' => $request->nullableInt('method_id'),
        ];

        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginate($request, 25);

        $total = $this->payments->countSearch($filters);

        return $this->renderAdmin('admin/payments/index', [
            'payments'   => $this->payments->search($filters, $perPage, $offset),
            'filters'    => $filters,
            'statuses'   => ProofStatus::cases(),
            'methods'    => $this->payments->allMethods(),
            'pendingCount' => $this->payments->countPendingReview(),
            'pagination' => $this->paginationMeta($total, $page, $perPage),
            'canVerify'  => $this->requireUser()->can('payments.verify'),
            'meta'       => ['title' => 'Payments', 'noindex' => true],
        ]);
    }

    public function show(Request $request): Response
    {
        $payment = $this->payments->findById($request->routeInt('id'));

        if ($payment === null) {
            throw HttpException::notFound();
        }

        $appointment = $this->appointments->findById($payment->appointmentId);

        return $this->renderAdmin('admin/payments/show', [
            'payment'     => $payment,
            'appointment' => $appointment,
            // Surfaced before the approve button: a slip already submitted on
            // another booking is the signature of a reused receipt.
            'duplicates'  => $this->paymentService->duplicatesOf($payment),
            'otherPayments' => $appointment !== null
                ? $this->payments->forAppointment($appointment->id)
                : [],
            'history'     => $this->audit->forTarget('payment', $payment->id),
            'canVerify'   => $this->requireUser()->can('payments.verify'),
            'meta'        => ['title' => 'Payment #' . $payment->id, 'noindex' => true],
        ]);
    }

    /**
     * Stream a proof file.
     *
     * The file lives outside the webroot, so this action is the only way to
     * reach it. Each access is written to the audit trail: these are patient
     * financial documents, and who looked at them matters.
     */
    public function proof(Request $request): Response
    {
        $payment = $this->payments->findById($request->routeInt('id'));

        if ($payment === null) {
            throw HttpException::notFound();
        }

        $absolute = $this->paymentService->resolveProofFile($payment);

        if ($absolute === null) {
            throw HttpException::notFound();
        }

        $this->audit->record(
            AuditLogger::PAYMENT_PROOF_VIEWED,
            'payment',
            $payment->id,
            'Viewed proof file',
        );

        return Response::file(
            $absolute,
            $payment->proofMime ?? 'application/octet-stream',
            $payment->proofOriginalName ?? ('proof-' . $payment->id),
            forceDownload: $request->bool('download'),
        );
    }

    public function verify(Request $request): Response
    {
        $user = $this->requireUser();
        $id   = $request->routeInt('id');

        try {
            $this->paymentService->verify($id, $user->id, $request->input('admin_note'));
        } catch (BookingException $e) {
            return $this->redirectWithError($this->config->adminPath . '/payments/' . $id, $e->getMessage());
        }

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/payments',
            'Payment verified and the patient has been notified.',
        );
    }

    public function reject(Request $request): Response
    {
        $user = $this->requireUser();
        $id   = $request->routeInt('id');

        try {
            $this->paymentService->reject($id, $user->id, $request->string('rejection_reason'));
        } catch (ValidationException $e) {
            return $this->redirectWithValidation(
                $this->config->adminPath . '/payments/' . $id,
                $e,
                $request,
            );
        } catch (BookingException $e) {
            return $this->redirectWithError($this->config->adminPath . '/payments/' . $id, $e->getMessage());
        }

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/payments',
            'Payment rejected and the patient has been asked to re-submit.',
        );
    }

    // -----------------------------------------------------------------
    //  Transfer instructions
    // -----------------------------------------------------------------

    public function methods(Request $request): Response
    {
        return $this->renderAdmin('admin/payments/methods', [
            'methods'  => $this->payments->allMethods(),
            'channels' => PaymentChannel::all(),
            'meta'     => ['title' => 'Payment Methods', 'noindex' => true],
        ]);
    }

    public function storeMethod(Request $request): Response
    {
        $id = $request->nullableInt('id');

        $data = [
            'channel'          => (PaymentChannel::tryFrom($request->string('channel')) ?? PaymentChannel::BANK_TRANSFER)->value,
            'provider'         => $request->string('provider'),
            'provider_am'      => $request->input('provider_am'),
            'account_name'     => $request->input('account_name'),
            'account_number'   => $request->input('account_number'),
            'branch'           => $request->input('branch'),
            'instructions'     => $request->input('instructions'),
            'instructions_am'  => $request->input('instructions_am'),
            'requires_proof'   => $request->bool('requires_proof') ? 1 : 0,
            'status'           => $request->bool('active') ? 'active' : 'inactive',
            'sort_order'       => $request->int('sort_order', 0),
        ];

        if ($data['provider'] === '') {
            return $this->redirectWithError(
                $this->config->adminPath . '/payments/methods',
                'Please enter the provider name.',
            );
        }

        if ($id !== null) {
            $before = $this->payments->findMethod($id);
            $this->payments->updateMethod($id, $data);

            $this->audit->record(
                AuditLogger::CONTENT_UPDATED,
                'payment_method',
                $id,
                'Updated payment method ' . $data['provider'],
            );

            $message = 'Payment method updated.';
        } else {
            $newId = $this->payments->createMethod($data);

            $this->audit->record(
                AuditLogger::CONTENT_CREATED,
                'payment_method',
                $newId,
                'Added payment method ' . $data['provider'],
            );

            $message = 'Payment method added.';
        }

        return $this->redirectWithSuccess($this->config->adminPath . '/payments/methods', $message);
    }

    public function deleteMethod(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->payments->deleteMethod($id);

        $this->audit->record(AuditLogger::CONTENT_DELETED, 'payment_method', $id, 'Removed payment method');

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/payments/methods',
            'Payment method removed.',
        );
    }
}
