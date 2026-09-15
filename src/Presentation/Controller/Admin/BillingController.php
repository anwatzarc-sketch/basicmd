<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\LedgerCategory;
use Aster\Domain\Enum\LedgerMode;
use Aster\Domain\Enum\ReceivablePaymentMethod;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\Services\BillingService;
use Aster\Domain\ValueObject\ReceiptId;
use Aster\Domain\ValueObject\VisitNumber;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use DateTimeImmutable;

/**
 * Manual accountant consumption logging and payment posting (FRS 10.4/10.5).
 *
 * Both actions require identifying an encounter by its visit number first -
 * there is no "charge a patient" without a specific clinical encounter to
 * attribute it to, which is the whole reason encounters exist rather than
 * billing directly off an appointment.
 */
final class BillingController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly EncounterRepositoryInterface $encounters,
        private readonly LedgerRepositoryInterface $ledger,
        private readonly ReceivablePaymentRepositoryInterface $payments,
        private readonly NumberSequenceInterface $sequence,
        private readonly BillingService $billing,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function ledger(Request $request): Response
    {
        $visitValue = $request->input('visit');
        $encounter  = null;

        if ($visitValue !== null && $visitValue !== '') {
            $visitNumber = VisitNumber::tryFrom($visitValue);
            $encounter   = $visitNumber !== null ? $this->encounters->findByVisitNumber($visitNumber) : null;
        }

        return $this->renderAdmin('admin/billing/ledger', [
            'searchTerm'    => $visitValue ?? '',
            'notFound'      => $visitValue !== null && $visitValue !== '' && $encounter === null,
            'encounter'     => $encounter,
            'entries'       => $encounter !== null ? $this->ledger->forEncounter($encounter->id) : [],
            'payments'      => $encounter !== null ? $this->payments->forEncounter($encounter->id) : [],
            'balance'       => $encounter !== null ? $this->billing->calculateReceivableBalance($encounter->id) : null,
            'categories'    => LedgerCategory::all(),
            'modes'         => LedgerMode::all(),
            'paymentMethods' => ReceivablePaymentMethod::all(),
            'meta'          => ['title' => 'Consumption Ledger', 'noindex' => true],
        ]);
    }

    public function postLedgerEntry(Request $request): Response
    {
        $user     = $this->requireUser();
        $visitNumber = VisitNumber::tryFrom($request->string('patient_visit_number'));
        $back     = $this->config->adminPath . '/billing/ledger'
            . ($visitNumber !== null ? '?visit=' . urlencode($visitNumber->value) : '');

        if ($visitNumber === null) {
            return $this->redirectWithError($back, 'A valid encounter visit number is required.');
        }

        $encounter = $this->encounters->findByVisitNumber($visitNumber);

        if ($encounter === null) {
            throw HttpException::notFound();
        }

        $category    = LedgerCategory::tryFrom($request->string('category'));
        $mode        = LedgerMode::tryFrom($request->string('mode'));
        $costEntry   = $request->string('cost_entry');
        $unit        = $request->int('unit', 1);
        $perUnitCost = $request->float('per_unit_cost');
        $reason      = $request->string('reason');

        if ($category === null || $mode === null || $costEntry === '' || $reason === '' || $unit < 1 || $perUnitCost < 0) {
            return $this->redirectWithError($back, 'Please fill in every field with a valid value.');
        }

        $this->ledger->create([
            'encounter_id'   => $encounter->id,
            'accountant_id'  => $user->id,
            'category'       => $category->value,
            'cost_entry'     => $costEntry,
            'unit'           => $unit,
            'per_unit_cost'  => number_format($perUnitCost, 2, '.', ''),
            'reason'         => $reason,
            'mode'           => $mode->value,
        ]);

        $this->audit->record(
            AuditLogger::CONTENT_CREATED,
            'consumption_ledger',
            $encounter->id,
            sprintf('Charged %s: %s x%d @ %.2f', $encounter->patientVisitNumber->value, $costEntry, $unit, $perUnitCost),
        );

        return $this->redirectWithSuccess($back, 'Charge recorded.');
    }

    public function postPayment(Request $request): Response
    {
        $user        = $this->requireUser();
        $visitNumber = VisitNumber::tryFrom($request->string('patient_visit_number'));
        $back        = $this->config->adminPath . '/billing/ledger'
            . ($visitNumber !== null ? '?visit=' . urlencode($visitNumber->value) : '');

        if ($visitNumber === null) {
            return $this->redirectWithError($back, 'A valid encounter visit number is required.');
        }

        $encounter = $this->encounters->findByVisitNumber($visitNumber);

        if ($encounter === null) {
            throw HttpException::notFound();
        }

        $method = ReceivablePaymentMethod::tryFrom($request->string('payment_method'));
        $amount = $request->float('amount_paid');

        if ($method === null || $amount <= 0) {
            return $this->redirectWithError($back, 'Please select a payment method and enter a positive amount.');
        }

        $today     = new DateTimeImmutable();
        $receiptId = ReceiptId::forDate($today, $this->sequence->next('receipt:' . $today->format('Ymd')));

        $this->payments->create([
            'receipt_id'      => $receiptId->value,
            'encounter_id'    => $encounter->id,
            'accountant_id'   => $user->id,
            'amount_paid'     => number_format($amount, 2, '.', ''),
            'payment_method'  => $method->value,
            'reference_note'  => $request->input('reference_note'),
        ]);

        $this->audit->record(
            AuditLogger::CONTENT_CREATED,
            'receivable_payment',
            $encounter->id,
            sprintf('Posted payment %s: ETB %.2f via %s', $receiptId->value, $amount, $method->value),
        );

        return $this->redirectWithSuccess($back, sprintf('Payment posted. Receipt %s.', $receiptId->value));
    }
}
