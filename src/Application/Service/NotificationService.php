<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\Entity\Appointment;
use Aster\Domain\Entity\ContactInquiry;
use Aster\Domain\Entity\Payment;
use Aster\Domain\Enum\Locale;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Mail\MailRenderer;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Env;
use Aster\Infrastructure\Support\Translator;

/**
 * Composes every patient- and staff-facing email.
 *
 * This is the retention engine the brief specified as SMS, rebuilt on email:
 * booking confirmation, a reminder 24 hours before the visit, a follow-up
 * afterwards, and the full payment-verification conversation.
 *
 * Every message is rendered in the patient's own locale - the one they had
 * selected when they booked, stored on the appointment row - rather than in
 * whatever language the staff member who triggered it happens to be using.
 */
final readonly class NotificationService
{
    public function __construct(
        private MailQueue $queue,
        private MailRenderer $renderer,
        private Translator $translator,
        private Config $config,
        private string $clinicName,
        private string $clinicAddress,
        private string $supportPhone,
    ) {
    }

    /**
     * Render in the patient's locale without disturbing the request's own.
     *
     * The translator is shared for the whole request, so it is switched,
     * used, and switched back. Forgetting the restore would leave the rest
     * of the page rendering in the patient's language instead of the staff
     * member's.
     *
     * @template T
     * @param callable(Translator): T $callback
     * @return T
     */
    private function inLocale(Locale $locale, callable $callback): mixed
    {
        $previous = $this->translator->locale();
        $this->translator->setLocale($locale);

        try {
            return $callback($this->translator);
        } finally {
            $this->translator->setLocale($previous);
        }
    }

    /**
     * Standard detail table for an appointment.
     *
     * @return list<array{label:string, value:string}>
     */
    private function appointmentDetails(Appointment $appointment, Translator $t, bool $withMoney = true): array
    {
        $details = [
            ['label' => $t->get('email.label_service'), 'value' => $appointment->subjectLabel()],
            ['label' => $t->get('email.label_doctor'),  'value' => $appointment->doctorLabel()],
            ['label' => $t->get('email.label_date'),    'value' => $t->date($appointment->date, true)],
            ['label' => $t->get('email.label_time'),    'value' => $appointment->timeSlot->label()],
        ];

        if ($appointment->isExpress()) {
            $details[] = [
                'label' => $t->get('booking.tier'),
                'value' => $t->get('queue_tier.express'),
            ];
        }

        if ($withMoney && $appointment->totalAmount->isPositive()) {
            $details[] = [
                'label' => $t->get('email.label_amount'),
                'value' => $t->money($appointment->totalAmount),
            ];

            if ($appointment->amountPaid->isPositive()) {
                $details[] = [
                    'label' => $t->get('email.label_paid'),
                    'value' => $t->money($appointment->amountPaid),
                ];
            }

            $balance = $appointment->balanceDue();

            if ($balance->isPositive() && $appointment->amountPaid->isPositive()) {
                $details[] = [
                    'label' => $t->get('email.label_balance'),
                    'value' => $t->money($balance),
                ];
            }
        }

        $details[] = ['label' => $t->get('email.label_location'), 'value' => $this->clinicAddress];

        return $details;
    }

    private function bookingUrl(Appointment $appointment): string
    {
        return $this->config->url('booking/' . rawurlencode($appointment->reference->value));
    }

    private function paymentUrl(Appointment $appointment): string
    {
        return $this->config->url('booking/' . rawurlencode($appointment->reference->value) . '/pay');
    }

    // -----------------------------------------------------------------
    //  Patient notifications
    // -----------------------------------------------------------------

    /** Sent the moment a booking is created. */
    public function bookingConfirmation(Appointment $appointment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment): void {
            $needsPayment = $appointment->totalAmount->isPositive()
                && $appointment->paymentStatus->acceptsProof();

            $extra = $this->renderer->referenceBlock(
                $appointment->reference->formatted(),
                $t->get('confirmation.reference'),
                $t->get('confirmation.ref_hint'),
            );

            $extra .= $this->renderer->stepList($t->get('confirmation.what_next'), [
                $t->get('confirmation.next_1'),
                $t->get('confirmation.next_2'),
                $t->get('confirmation.next_3'),
            ]);

            $html = $this->renderer->render(
                locale:    $appointment->locale,
                heading:   $t->get('email.booked_heading'),
                greeting:  $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:     $t->get('email.booked_intro'),
                details:   $this->appointmentDetails($appointment, $t),
                primaryCta: $needsPayment
                    ? ['url' => $this->paymentUrl($appointment), 'label' => $t->get('email.pay_now')]
                    : ['url' => $this->bookingUrl($appointment), 'label' => $t->get('email.view_booking')],
                extraHtml: $extra,
            );

            $this->queue->enqueue(
                mailable:    'booking_confirmation',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.booked_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    /** Sent when staff move a booking from pending to confirmed. */
    public function appointmentConfirmed(Appointment $appointment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment): void {
            $html = $this->renderer->render(
                locale:     $appointment->locale,
                heading:    $t->get('email.confirmed_heading'),
                greeting:   $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:      $t->get('email.confirmed_intro'),
                details:    $this->appointmentDetails($appointment, $t),
                primaryCta: ['url' => $this->bookingUrl($appointment), 'label' => $t->get('email.view_booking')],
                extraHtml:  $this->renderer->referenceBlock(
                    $appointment->reference->formatted(),
                    $t->get('confirmation.reference'),
                    $t->get('confirmation.ref_hint'),
                ),
            );

            $this->queue->enqueue(
                mailable:    'appointment_confirmed',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.confirmed_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    /**
     * The 24-hour reminder - the single highest-value message in the system.
     * This is what the brief's SMS engine was for: cutting no-shows.
     */
    public function appointmentReminder(Appointment $appointment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment): void {
            $notice = null;

            // If money is still outstanding, the reminder is also the last
            // good chance to collect it before the patient arrives.
            if ($appointment->balanceDue()->isPositive()) {
                $notice = htmlspecialchars(
                    $t->get('payment.balance_note', ['amount' => $t->money($appointment->balanceDue())]),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                );
            }

            $html = $this->renderer->render(
                locale:     $appointment->locale,
                heading:    $t->get('email.reminder_heading'),
                greeting:   $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:      $t->get('email.reminder_intro'),
                details:    $this->appointmentDetails($appointment, $t),
                primaryCta: ['url' => $this->bookingUrl($appointment), 'label' => $t->get('email.view_booking')],
                extraHtml:  '<p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#64748b;">'
                    . htmlspecialchars($t->get('email.reminder_tips'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</p>',
                noticeHtml: $notice,
            );

            $this->queue->enqueue(
                mailable:    'reminder_24h',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.reminder_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    /** Post-visit follow-up, which also drives repeat bookings. */
    public function appointmentFollowup(Appointment $appointment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment): void {
            $html = $this->renderer->render(
                locale:     $appointment->locale,
                heading:    $t->get('email.followup_heading'),
                greeting:   $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:      $t->get('email.followup_intro'),
                details:    [],
                primaryCta: ['url' => $this->config->url('book'), 'label' => $t->get('email.followup_cta')],
            );

            $this->queue->enqueue(
                mailable:    'followup',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.followup_subject'),
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    public function appointmentCancelled(Appointment $appointment, ?string $reason = null): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment, $reason): void {
            $details = $this->appointmentDetails($appointment, $t, withMoney: false);

            if ($reason !== null && $reason !== '') {
                $details[] = ['label' => $t->get('email.label_reason'), 'value' => $reason];
            }

            $html = $this->renderer->render(
                locale:     $appointment->locale,
                heading:    $t->get('email.cancelled_heading'),
                greeting:   $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:      $t->get('email.cancelled_intro'),
                details:    $details,
                primaryCta: ['url' => $this->config->url('book'), 'label' => $t->get('nav.book')],
            );

            $this->queue->enqueue(
                mailable:    'appointment_cancelled',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.cancelled_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    // -----------------------------------------------------------------
    //  Payment notifications
    // -----------------------------------------------------------------

    /** Acknowledge a proof upload, so the patient knows it arrived. */
    public function paymentReceived(Appointment $appointment, Payment $payment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment, $payment): void {
            $html = $this->renderer->render(
                locale:   $appointment->locale,
                heading:  $t->get('email.payment_received_heading'),
                greeting: $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:    $t->get('email.payment_received_intro'),
                details:  [
                    ['label' => $t->get('email.label_reference'), 'value' => $appointment->reference->formatted()],
                    ['label' => $t->get('email.label_amount'),    'value' => $t->money($payment->amount)],
                    ['label' => $t->get('payment.transfer_ref'),  'value' => $payment->transferRef ?? '-'],
                    ['label' => $t->get('email.label_status'),    'value' => $t->get('payment.status_submitted')],
                ],
                primaryCta: ['url' => $this->bookingUrl($appointment), 'label' => $t->get('email.view_booking')],
            );

            $this->queue->enqueue(
                mailable:    'payment_received',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.payment_received_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'payment',
                relatedId:   $payment->id,
            );
        });
    }

    public function paymentVerified(Appointment $appointment, Payment $payment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment, $payment): void {
            $details = [
                ['label' => $t->get('email.label_reference'), 'value' => $appointment->reference->formatted()],
                ['label' => $t->get('email.label_amount'),    'value' => $t->money($payment->amount)],
                ['label' => $t->get('email.label_status'),    'value' => $t->get('payment.status_verified')],
            ];

            $balance = $appointment->balanceDue();

            if ($balance->isPositive()) {
                $details[] = ['label' => $t->get('email.label_balance'), 'value' => $t->money($balance)];
            }

            $html = $this->renderer->render(
                locale:     $appointment->locale,
                heading:    $t->get('email.payment_verified_heading'),
                greeting:   $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:      $t->get('email.payment_verified_intro'),
                details:    $details,
                primaryCta: ['url' => $this->bookingUrl($appointment), 'label' => $t->get('email.view_booking')],
                noticeHtml: $balance->isPositive()
                    ? htmlspecialchars(
                        $t->get('payment.balance_note', ['amount' => $t->money($balance)]),
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8',
                    )
                    : null,
            );

            $this->queue->enqueue(
                mailable:    'payment_verified',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.payment_verified_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'payment',
                relatedId:   $payment->id,
            );
        });
    }

    /**
     * Tell the patient their slip was rejected and exactly what to do.
     *
     * The rejection reason is mandatory upstream, so this message always
     * carries an actionable explanation rather than a bare refusal.
     */
    public function paymentRejected(Appointment $appointment, Payment $payment): void
    {
        if (!$appointment->hasEmail()) {
            return;
        }

        $this->inLocale($appointment->locale, function (Translator $t) use ($appointment, $payment): void {
            $html = $this->renderer->render(
                locale:   $appointment->locale,
                heading:  $t->get('email.payment_rejected_heading'),
                greeting: $t->get('email.greeting', ['name' => $appointment->patientName]),
                intro:    $t->get('email.payment_rejected_intro'),
                details:  [
                    ['label' => $t->get('email.label_reference'), 'value' => $appointment->reference->formatted()],
                    ['label' => $t->get('email.label_amount'),    'value' => $t->money($payment->amount)],
                ],
                primaryCta: ['url' => $this->paymentUrl($appointment), 'label' => $t->get('payment.resubmit')],
                extraHtml:  '<p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#64748b;">'
                    . htmlspecialchars($t->get('email.payment_rejected_action'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</p>',
                noticeHtml: htmlspecialchars(
                    $t->get('payment.rejected_note', ['reason' => $payment->rejectionReason ?? '']),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                ),
            );

            $this->queue->enqueue(
                mailable:    'payment_rejected',
                toEmail:     $appointment->patientEmail,
                toName:      $appointment->patientName,
                subject:     $t->get('email.payment_rejected_subject', ['ref' => $appointment->reference->value]),
                htmlBody:    $html,
                relatedType: 'payment',
                relatedId:   $payment->id,
            );
        });
    }

    // -----------------------------------------------------------------
    //  Contact form
    // -----------------------------------------------------------------

    public function inquiryAcknowledgement(ContactInquiry $inquiry): void
    {
        if ($inquiry->email === null) {
            return;
        }

        $this->inLocale($inquiry->locale, function (Translator $t) use ($inquiry): void {
            $html = $this->renderer->render(
                locale:   $inquiry->locale,
                heading:  $t->get('email.inquiry_heading'),
                greeting: $t->get('email.greeting', ['name' => $inquiry->name]),
                intro:    $t->get('email.inquiry_intro'),
            );

            $this->queue->enqueue(
                mailable:    'inquiry_ack',
                toEmail:     $inquiry->email,
                toName:      $inquiry->name,
                subject:     $t->get('email.inquiry_subject'),
                htmlBody:    $html,
                relatedType: 'inquiry',
                relatedId:   $inquiry->id,
            );
        });
    }

    // -----------------------------------------------------------------
    //  Internal staff alerts
    //
    //  Always English: staff inboxes are shared, and these are operational
    //  notices rather than patient communication.
    // -----------------------------------------------------------------

    public function notifyStaffNewBooking(Appointment $appointment): void
    {
        $inbox = Env::get('MAIL_ADMIN_INBOX');

        if ($inbox === null || $inbox === '') {
            return;
        }

        $this->inLocale(Locale::EN, function (Translator $t) use ($appointment, $inbox): void {
            $html = $this->renderer->render(
                locale:   Locale::EN,
                heading:  'New booking received',
                greeting: 'Hello team,',
                intro:    sprintf(
                    'A new %s booking was made via the website by %s.',
                    $appointment->queueTier->label(),
                    $appointment->patientName,
                ),
                details:  [
                    ['label' => 'Reference', 'value' => $appointment->reference->value],
                    ['label' => 'Patient',   'value' => $appointment->patientName],
                    ['label' => 'Phone',     'value' => $appointment->patientPhone->formatNational()],
                    ['label' => 'Service',   'value' => $appointment->subjectLabel()],
                    ['label' => 'Doctor',    'value' => $appointment->doctorLabel()],
                    ['label' => 'Date',      'value' => $t->date($appointment->date, true)],
                    ['label' => 'Time',      'value' => $appointment->timeSlot->label()],
                    ['label' => 'Amount',    'value' => $t->money($appointment->totalAmount)],
                ],
                primaryCta: [
                    'url'   => $this->config->adminUrl('appointments/' . $appointment->id),
                    'label' => 'Open in admin',
                ],
            );

            $this->queue->enqueue(
                mailable:    'staff_new_booking',
                toEmail:     $inbox,
                toName:      'Aster Front Desk',
                subject:     '[New booking] ' . $appointment->reference->value . ' - ' . $appointment->patientName,
                htmlBody:    $html,
                relatedType: 'appointment',
                relatedId:   $appointment->id,
            );
        });
    }

    /** Alert Finance that a slip is waiting for review. */
    public function notifyFinanceNewProof(Appointment $appointment, Payment $payment, int $duplicateCount = 0): void
    {
        $inbox = Env::get('MAIL_FINANCE_INBOX') ?: Env::get('MAIL_ADMIN_INBOX');

        if ($inbox === null || $inbox === '') {
            return;
        }

        $this->inLocale(Locale::EN, function (Translator $t) use ($appointment, $payment, $inbox, $duplicateCount): void {
            // Flag a slip already seen on another booking, which is the
            // signature of a receipt being reused.
            $notice = $duplicateCount > 0
                ? sprintf(
                    '<strong>Duplicate warning:</strong> this exact file has already been submitted on %d other booking%s. Verify carefully before approving.',
                    $duplicateCount,
                    $duplicateCount === 1 ? '' : 's',
                )
                : null;

            $html = $this->renderer->render(
                locale:   Locale::EN,
                heading:  'Payment proof awaiting verification',
                greeting: 'Hello Finance,',
                intro:    sprintf('%s uploaded a receipt for booking %s.', $appointment->patientName, $appointment->reference->value),
                details:  [
                    ['label' => 'Reference',   'value' => $appointment->reference->value],
                    ['label' => 'Patient',     'value' => $appointment->patientName],
                    ['label' => 'Method',      'value' => $payment->methodLabel ?? 'Unspecified'],
                    ['label' => 'Amount sent', 'value' => $t->money($payment->amount)],
                    ['label' => 'Booking total','value'=> $t->money($appointment->totalAmount)],
                    ['label' => 'Transaction', 'value' => $payment->transferRef ?? '-'],
                    ['label' => 'Payer name',  'value' => $payment->payerName ?? '-'],
                ],
                primaryCta: [
                    'url'   => $this->config->adminUrl('payments/' . $payment->id),
                    'label' => 'Review payment',
                ],
                noticeHtml: $notice,
            );

            $this->queue->enqueue(
                mailable:    'staff_new_proof',
                toEmail:     $inbox,
                toName:      'Aster Finance',
                subject:     '[Payment review] ' . $appointment->reference->value . ' - ' . $t->money($payment->amount),
                htmlBody:    $html,
                relatedType: 'payment',
                relatedId:   $payment->id,
            );
        });
    }

    public function notifyStaffNewInquiry(ContactInquiry $inquiry): void
    {
        $inbox = Env::get('MAIL_ADMIN_INBOX');

        if ($inbox === null || $inbox === '') {
            return;
        }

        $this->inLocale(Locale::EN, function (Translator $t) use ($inquiry, $inbox): void {
            $html = $this->renderer->render(
                locale:   Locale::EN,
                heading:  'New patient enquiry',
                greeting: 'Hello team,',
                intro:    $inquiry->preview(400),
                details:  [
                    ['label' => 'Name',    'value' => $inquiry->name],
                    ['label' => 'Phone',   'value' => $inquiry->phone->formatNational()],
                    ['label' => 'Email',   'value' => $inquiry->email ?? '-'],
                    ['label' => 'Subject', 'value' => $inquiry->subjectLine()],
                ],
                primaryCta: [
                    'url'   => $this->config->adminUrl('inquiries/' . $inquiry->id),
                    'label' => 'Open enquiry',
                ],
            );

            $this->queue->enqueue(
                mailable:    'staff_new_inquiry',
                toEmail:     $inbox,
                toName:      'Aster Front Desk',
                subject:     '[Enquiry] ' . $inquiry->subjectLine() . ' - ' . $inquiry->name,
                htmlBody:    $html,
                relatedType: 'inquiry',
                relatedId:   $inquiry->id,
            );
        });
    }
}
