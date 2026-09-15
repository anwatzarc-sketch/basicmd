<?php
/**
 * Privacy policy.
 *
 * Required wherever patient data is collected. The content below describes
 * what this application actually does - it is not boilerplate, and it should
 * be reviewed by the clinic's legal adviser before launch.
 *
 * @var \Aster\Presentation\View\View $view
 */

declare(strict_types=1);

$email = $settings->string('email_public', '');
$phone = $settings->string('phone_primary', '');
?>
<section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:py-16">
    <h1 class="text-3xl font-extrabold tracking-tight text-medical-900 sm:text-4xl"><?= $view->t('footer.privacy') ?></h1>
    <p class="mt-3 text-sm text-slate-500">Last updated: <?= date('d F Y') ?></p>

    <div class="prose-article mt-8">
        <h2>What we collect</h2>
        <p>When you book an appointment we collect your name, phone number, optional email address, the service or package you selected, your preferred date and time, and any symptoms or notes you choose to share. If you upload a payment receipt we also store that file and the transaction reference shown on it.</p>

        <h2>Why we collect it</h2>
        <ul>
            <li><strong>To deliver care.</strong> Your contact details let our intake team confirm your appointment and reach you if anything changes.</li>
            <li><strong>To process payment.</strong> Receipts are reviewed by our finance team to confirm your booking is paid.</li>
            <li><strong>To remind you.</strong> If you give us an email address we send a booking confirmation, a reminder before your appointment, and a follow-up afterwards.</li>
        </ul>

        <h2>Who can see it</h2>
        <p>Access is restricted by role. Reception staff see scheduling information; finance staff see payment records; a doctor sees only the appointments assigned to them. Every access to a payment document and every change to an appointment is recorded in an internal audit log.</p>

        <h2>How long we keep it</h2>
        <p>Appointment and payment records are retained as required for medical and financial record-keeping under Ethiopian law. Application logs are kept for 30 days. Payment receipt files are stored outside the public web directory and are never accessible from the internet without staff authentication.</p>

        <h2>What we do not do</h2>
        <p>We do not sell your information. We do not share it with advertisers. We do not use third-party analytics or tracking scripts on pages that display your booking details.</p>

        <h2>Your choices</h2>
        <p>You may request a copy of the information we hold about you, ask us to correct it, or ask us to delete information we are not legally required to retain. You can cancel an upcoming appointment yourself from the booking page, or by calling us.</p>

        <h2>Contact</h2>
        <p>
            For any question about this policy or your information, contact us
            <?php if ($email !== ''): ?>at <a href="mailto:<?= $view->e($email) ?>"><?= $view->e($email) ?></a><?php endif; ?>
            <?php if ($phone !== ''): ?> or on <a href="tel:<?= $view->e($phone) ?>"><?= $view->e($phone) ?></a><?php endif; ?>.
        </p>
    </div>
</section>
