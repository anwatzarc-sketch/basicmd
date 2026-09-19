<?php
/**
 * Terms of service.
 *
 * @var \Aster\Presentation\View\View $view
 */

declare(strict_types=1);

$phone = $settings->string('phone_emergency', $settings->string('phone_primary', ''));
?>
<section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <h1 class="page-title"><?= $view->t('footer.terms') ?></h1>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Last updated: <?= date('d F Y') ?></p>

    <div class="prose-article mt-8">
        <h2>This website is not for emergencies</h2>
        <p>Do not use the online booking form for urgent medical problems. If you are experiencing a medical emergency, call
            <?php if ($phone !== ''): ?><a href="tel:<?= $view->e($phone) ?>"><?= $view->e($phone) ?></a><?php else: ?>our emergency line<?php endif; ?>
            or go to the nearest emergency department immediately.</p>

        <h2>Booking requests</h2>
        <p>Submitting the booking form reserves a slot subject to confirmation by our intake team. We will contact you to confirm the exact time. We may need to reschedule in the event of a clinical emergency, and we will always tell you as early as we can.</p>

        <h2>Payment</h2>
        <p>Where a deposit or full payment is required, your slot is held once our finance team verifies the receipt you upload. Verification normally completes within one working day. If a receipt cannot be verified we will tell you why and ask for a corrected one.</p>

        <h2>Cancellation</h2>
        <p>You may cancel an appointment online up to two hours before its start time using your booking reference and the phone number you booked with. Inside that window, please call us so we can reassign the slot.</p>

        <h2>Health information on this site</h2>
        <p>Articles in our Health Hub are written for general education. They are reviewed by our clinicians but they are not a diagnosis and do not replace a consultation. Always speak to a qualified clinician about your own situation.</p>

        <h2>Accuracy of the information you give us</h2>
        <p>Please give accurate contact details. We use your phone number and email address to confirm and remind you about appointments, and we cannot reach you if they are wrong.</p>
    </div>

    <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-8 dark:border-slate-800">
        <a href="<?= $view->url('contact') ?>" class="btn-secondary"><?= $view->t('nav.contact') ?></a>
        <a href="<?= $view->url('privacy') ?>" class="btn-ghost"><?= $view->t('footer.privacy') ?></a>
    </div>
</section>
