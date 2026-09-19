<?php

declare(strict_types=1);

namespace MediCareMini\Application\Service;

use MediCareMini\Domain\Entity\Appointment;
use MediCareMini\Domain\Entity\Payment;
use MediCareMini\Domain\Enum\PaymentKind;
use MediCareMini\Domain\Enum\ProofStatus;
use MediCareMini\Domain\Exception\BookingException;
use MediCareMini\Domain\Exception\ValidationException;
use MediCareMini\Domain\ValueObject\Money;
use MediCareMini\Infrastructure\Persistence\AppointmentRepository;
use MediCareMini\Infrastructure\Persistence\AuditLogger;
use MediCareMini\Infrastructure\Persistence\PaymentRepository;
use MediCareMini\Infrastructure\Storage\FileUploader;
use MediCareMini\Infrastructure\Support\Logger;
use MediCareMini\Presentation\Http\Request;
use DateTimeImmutable;
use Throwable;

/**
 * Manual payment verification.
 *
 * This is what stands in for a payment gateway. The patient transfers money
 * out of band, uploads evidence, and a Finance officer approves or rejects
 * it. The flow's integrity rests on three things:
 *
 *  1. The appointment's `amount_paid` is never incremented. It is recomputed
 *     from the set of verified payment rows, so a rejected-then-corrected
 *     slip cannot leave a wrong running total.
 *  2. Every uploaded file is hashed, and a hash seen on another booking is
 *     surfaced to Finance before approval. Reusing one receipt across two
 *     bookings is the obvious attack on a manual flow.
 *  3. Approve and reject both run under a row lock, so two officers looking
 *     at the same slip cannot both act on it.
 */
final readonly class PaymentService
{
    public function __construct(
        private PaymentRepository $payments,
        private AppointmentRepository $appointments,
        private FileUploader $uploader,
        private NotificationService $notifications,
        private AuditLogger $audit,
        private Logger $logger,
    ) {
    }

    /**
     * Accept a proof-of-payment submission from a patient.
     *
     * @throws ValidationException
     * @throws BookingException
     */
    public function submitProof(Appointment $appointment, Request $request, bool $trustProxy = false): Payment
    {
        if (!$appointment->paymentStatus->acceptsProof()) {
            throw BookingException::alreadyFinalised($appointment->status);
        }

        $methodId = $request->nullableInt('payment_method_id');
        $method   = $methodId !== null ? $this->payments->findMethod($methodId) : null;

        if ($method === null) {
            throw ValidationException::single('payment_method_id', 'Please choose a payment method.');
        }

        // Cash at reception is recorded as an intent, with nothing to upload
        // and nothing for Finance to verify until the patient arrives.
        if (!$method->expectsProof()) {
            return $this->recordCashIntent($appointment, $method->id);
        }

        $errors = [];

        // --- Amount ---
        $rawAmount = $request->input('amount');
        $amount    = null;

        if ($rawAmount === null || !is_numeric($rawAmount)) {
            $errors['amount'][] = 'Please enter the amount you transferred.';
        } else {
            $amount = Money::fromMajor((float) $rawAmount);

            if (!$amount->isPositive()) {
                $errors['amount'][] = 'The amount must be greater than zero.';
            } elseif ($amount->greaterThan($appointment->totalAmount)) {
                // Overpayment is more often a typo than generosity, and
                // accepting it silently creates a refund obligation.
                $errors['amount'][] = 'That is more than the amount due. Please check the figure.';
            }
        }

        // --- Transfer reference ---
        $transferRef = $request->input('transfer_ref');

        if ($transferRef === null) {
            $errors['transfer_ref'][] = 'Please enter the transaction or receipt number.';
        } elseif (mb_strlen($transferRef) > 120) {
            $errors['transfer_ref'][] = 'That reference is too long.';
        }

        // --- Transfer date ---
        $transferredAt = null;
        $rawDate       = $request->input('transferred_at');

        if ($rawDate !== null) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);

            if ($parsed === false) {
                $errors['transferred_at'][] = 'Please enter a valid date.';
            } elseif ($parsed > new DateTimeImmutable('tomorrow')) {
                $errors['transferred_at'][] = 'The transfer date cannot be in the future.';
            } else {
                $transferredAt = $parsed;
            }
        }

        $payerName = $request->input('payer_name');

        // --- File ---
        $file = $request->file('proof');

        if ($file === null) {
            $errors['proof'][] = 'Please attach your receipt or screenshot.';
        }

        if ($errors !== []) {
            throw ValidationException::withErrors($errors);
        }

        assert($amount instanceof Money);
        assert(is_array($file));

        // Throws ValidationException on a bad file; nothing has been written
        // to the database at this point, so there is nothing to roll back.
        $stored = $this->uploader->storeProof($file, $appointment->reference->value);

        $kind = $this->determineKind($appointment, $amount);

        $paymentId = $this->payments->create([
            'appointment_id'    => $appointment->id,
            'payment_method_id' => $method->id,
            'kind'              => $kind->value,
            'amount'            => $amount->toDatabase(),
            'currency'          => 'ETB',
            'payer_name'        => $payerName,
            'transfer_ref'      => $transferRef,
            'transferred_at'    => $transferredAt?->format('Y-m-d'),
            'proof_path'        => $stored->relativePath,
            'proof_original'    => $stored->originalName,
            'proof_mime'        => $stored->mimeType,
            'proof_size'        => $stored->size,
            'proof_sha256'      => $stored->sha256,
            'status'            => ProofStatus::SUBMITTED->value,
            'submitted_ip'      => $request->ipBinary($trustProxy),
        ]);

        // Moves the appointment to awaiting_verification.
        $this->appointments->recalculatePayment($appointment->id);

        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            throw new \RuntimeException('Payment row vanished immediately after creation.');
        }

        $duplicates = $this->payments->findDuplicateProofs($stored->sha256, $paymentId);

        if ($duplicates !== []) {
            $this->logger->warning('Duplicate payment proof detected', [
                'payment_id'   => $paymentId,
                'booking_ref'  => $appointment->reference->value,
                'duplicates'   => count($duplicates),
            ]);
        }

        $this->audit->record(
            AuditLogger::PAYMENT_SUBMITTED,
            'payment',
            $paymentId,
            sprintf(
                'Proof submitted for %s: %s via %s',
                $appointment->reference->value,
                $amount->format(),
                $method->provider,
            ),
        );

        $refreshed = $this->appointments->findById($appointment->id) ?? $appointment;

        $this->safely(function () use ($refreshed, $payment, $duplicates): void {
            $this->notifications->paymentReceived($refreshed, $payment);
            $this->notifications->notifyFinanceNewProof($refreshed, $payment, count($duplicates));
        });

        return $payment;
    }

    /**
     * Record a "pay at reception" choice.
     *
     * Kept as a payments row so the front desk sees the patient's declared
     * intent, but left awaiting_proof: no money has moved yet.
     */
    private function recordCashIntent(Appointment $appointment, int $methodId): Payment
    {
        $paymentId = $this->payments->create([
            'appointment_id'    => $appointment->id,
            'payment_method_id' => $methodId,
            'kind'              => PaymentKind::FULL->value,
            'amount'            => $appointment->totalAmount->toDatabase(),
            'currency'          => 'ETB',
            'status'            => ProofStatus::AWAITING_PROOF->value,
            'admin_note'        => 'Patient chose to pay at reception.',
        ]);

        $payment = $this->payments->findById($paymentId);

        if ($payment === null) {
            throw new \RuntimeException('Payment row vanished immediately after creation.');
        }

        $this->audit->record(
            AuditLogger::PAYMENT_SUBMITTED,
            'payment',
            $paymentId,
            sprintf('Cash-on-arrival selected for %s', $appointment->reference->value),
        );

        return $payment;
    }

    /**
     * Classify a submission as deposit, balance or full payment.
     *
     * Decided from what is already verified plus what this slip covers, so a
     * patient paying in two instalments is labelled correctly without being
     * asked to categorise their own payment.
     */
    private function determineKind(Appointment $appointment, Money $amount): PaymentKind
    {
        if ($appointment->amountPaid->isPositive()) {
            return PaymentKind::BALANCE;
        }

        return $amount->amountMinor >= $appointment->totalAmount->amountMinor
            ? PaymentKind::FULL
            : PaymentKind::DEPOSIT;
    }

    // -----------------------------------------------------------------
    //  Finance review
    // -----------------------------------------------------------------

    /**
     * Approve a slip and credit the booking.
     *
     * @throws BookingException when another reviewer already resolved it
     */
    public function verify(int $paymentId, int $verifierId, ?string $note = null): Payment
    {
        $payment = $this->payments->verify($paymentId, $verifierId, $note);

        // Recompute from all verified rows rather than adding this one, so
        // the total is always derivable from the payments table alone.
        $this->appointments->recalculatePayment($payment->appointmentId);

        $appointment = $this->appointments->findById($payment->appointmentId);

        $this->audit->record(
            AuditLogger::PAYMENT_VERIFIED,
            'payment',
            $paymentId,
            sprintf(
                'Verified %s for %s',
                $payment->amount->format(),
                $appointment?->reference->value ?? ('#' . $payment->appointmentId),
            ),
        );

        if ($appointment !== null) {
            $this->safely(fn () => $this->notifications->paymentVerified($appointment, $payment));
        }

        return $payment;
    }

    /**
     * Reject a slip with a mandatory, patient-facing reason.
     *
     * @throws ValidationException when no reason is supplied
     * @throws BookingException    when another reviewer already resolved it
     */
    public function reject(int $paymentId, int $verifierId, string $reason): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::single(
                'rejection_reason',
                'Please explain why this payment was rejected - the patient will see this message.',
            );
        }

        $payment = $this->payments->reject($paymentId, $verifierId, $reason);

        $this->appointments->recalculatePayment($payment->appointmentId);

        $appointment = $this->appointments->findById($payment->appointmentId);

        $this->audit->record(
            AuditLogger::PAYMENT_REJECTED,
            'payment',
            $paymentId,
            sprintf(
                'Rejected %s for %s: %s',
                $payment->amount->format(),
                $appointment?->reference->value ?? ('#' . $payment->appointmentId),
                $reason,
            ),
        );

        if ($appointment !== null) {
            $this->safely(fn () => $this->notifications->paymentRejected($appointment, $payment));
        }

        return $payment;
    }

    /**
     * Earlier submissions sharing this file's digest.
     *
     * @return list<array<string, mixed>>
     */
    public function duplicatesOf(Payment $payment): array
    {
        if ($payment->proofSha256 === null) {
            return [];
        }

        return $this->payments->findDuplicateProofs($payment->proofSha256, $payment->id);
    }

    /**
     * Absolute path of a proof file, or null if it is missing or escapes the
     * storage root.
     */
    public function resolveProofFile(Payment $payment): ?string
    {
        if ($payment->proofPath === null) {
            return null;
        }

        return $this->uploader->resolveProofPath($payment->proofPath);
    }

    /**
     * Run a notification block without letting it break the caller.
     *
     * The money movement is already committed by the time these run; failing
     * the request now would tell Finance the approval did not happen when it
     * did.
     */
    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->logger->error('Payment notification failed', ['error' => $e->getMessage()]);
        }
    }
}
