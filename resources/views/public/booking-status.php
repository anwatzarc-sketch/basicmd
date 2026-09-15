<?php
/**
 * Booking confirmation and status page.
 *
 * Reached straight after booking and from the confirmation email. Printable,
 * because patients bring a printout to reception.
 *
 * @var \Aster\Presentation\View\View      $view
 * @var \Aster\Domain\Entity\Appointment   $appointment
 * @var list<\Aster\Domain\Entity\Payment> $payments
 * @var bool                               $canCancel
 */

declare(strict_types=1);

$t         = $view->translator;
$balance   = $appointment->balanceDue();
$needsPay  = $balance->isPositive() && $appointment->paymentStatus->acceptsProof();
$ethiopian = $appointment->ethiopianDate();
?>

<section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">

    <!-- Success header -->
    <div class="text-center">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700" aria-hidden="true">
            &#10003;
        </div>
        <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-medical-900 sm:text-4xl">
            <?= $view->t('confirmation.title') ?>
        </h1>
        <p class="mt-3 text-slate-600">
            <?= $appointment->hasEmail()
                ? $view->t('confirmation.subtitle', ['email' => $appointment->patientEmail])
                : $view->t('confirmation.subtitle_no_email') ?>
        </p>
    </div>

    <!-- Reference -->
    <div class="mt-8 rounded-3xl border border-dashed border-medical-400 bg-medical-50 p-6 text-center">
        <span class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-medical-700">
            <?= $view->t('confirmation.reference') ?>
        </span>
        <div class="mt-2 font-mono text-3xl font-extrabold tracking-wider text-medical-900 sm:text-4xl">
            <?= $view->e($appointment->reference->formatted()) ?>
        </div>
        <p class="mt-3 text-xs text-slate-500"><?= $view->t('confirmation.ref_hint') ?></p>
        <button type="button" class="chip no-print mt-3"
                data-copy="<?= $view->e($appointment->reference->value) ?>"
                data-copied-label="<?= $view->e($view->tRaw('payment.copied')) ?>">
            <?= $view->t('payment.copy') ?>
        </button>
    </div>

    <!-- Details -->
    <div class="card mt-8 p-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-extrabold text-medical-900"><?= $view->t('booking.summary') ?></h2>
            <div class="flex flex-wrap gap-2">
                <span class="badge <?= $view->e($appointment->status->badgeClass()) ?>">
                    <?= $view->t($appointment->status->translationKey()) ?>
                </span>
                <?php if ($appointment->totalAmount->isPositive()): ?>
                    <span class="badge <?= $view->e($appointment->paymentStatus->badgeClass()) ?>">
                        <?= $view->t($appointment->paymentStatus->translationKey()) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <dl class="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_service') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($appointment->subjectLabel()) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_doctor') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($appointment->doctorLabel()) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_date') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900">
                    <?= $view->e($t->date($appointment->date, true)) ?>
                    <?php if (!$view->isAmharic()): ?>
                        <span class="mt-0.5 block text-xs font-normal text-slate-500">
                            <?= $view->t('common.ethiopian_date') ?>: <?= $view->e($ethiopian->format('en')) ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_time') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900">
                    <?= $view->e($appointment->timeSlot->label()) ?>
                    <?php if ($appointment->isExpress()): ?>
                        <span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?> ml-2">
                            <?= $view->t('queue_tier.express') ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('booking.name') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($appointment->patientName) ?></dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('booking.phone') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($appointment->patientPhone->formatNational()) ?></dd>
            </div>

            <?php if ($appointment->totalAmount->isPositive()): ?>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('booking.total') ?></dt>
                    <dd class="mt-1 text-lg font-extrabold text-medical-700"><?= $view->e($t->money($appointment->totalAmount)) ?></dd>
                </div>
                <?php if ($balance->isPositive()): ?>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_balance') ?></dt>
                        <dd class="mt-1 text-lg font-extrabold text-amber-700"><?= $view->e($t->money($balance)) ?></dd>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="sm:col-span-2">
                <dt class="text-xs font-bold uppercase tracking-wider text-slate-500"><?= $view->t('email.label_location') ?></dt>
                <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($settings->string('address', '')) ?></dd>
            </div>
        </dl>

        <div class="no-print mt-7 flex flex-wrap gap-3 border-t border-slate-200 pt-6">
            <?php if ($needsPay): ?>
                <a href="<?= $view->url('booking/' . $appointment->reference->value . '/pay') ?>" class="btn-primary">
                    <?= $view->t('confirmation.pay_now') ?>
                </a>
            <?php endif; ?>
            <?php /* data-print, not onclick: inline handlers are blocked by the CSP. */ ?>
            <button type="button" class="btn-secondary" data-print>
                <?= $view->t('confirmation.print') ?>
            </button>
        </div>
    </div>

    <!-- What happens next -->
    <?php if (!$appointment->status->isTerminal()): ?>
        <div class="card mt-6 p-6 sm:p-8">
            <h2 class="text-lg font-extrabold text-medical-900"><?= $view->t('confirmation.what_next') ?></h2>
            <ol class="mt-5 grid gap-4">
                <?php for ($step = 1; $step <= 3; $step++): ?>
                    <li class="flex gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-medical-600 text-xs font-bold text-white">
                            <?= $step ?>
                        </span>
                        <span class="text-sm leading-relaxed text-slate-600"><?= $view->t("confirmation.next_{$step}") ?></span>
                    </li>
                <?php endfor; ?>
            </ol>
        </div>
    <?php endif; ?>

    <!-- Payment history -->
    <?php if ($payments !== []): ?>
        <div class="card mt-6 p-6 sm:p-8">
            <h2 class="text-lg font-extrabold text-medical-900"><?= $view->t('payment.title') ?></h2>
            <ul class="mt-5 grid gap-3">
                <?php foreach ($payments as $payment): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 p-4">
                        <div>
                            <b class="text-slate-900"><?= $view->e($t->money($payment->amount)) ?></b>
                            <span class="block text-xs text-slate-500">
                                <?= $view->e($t->dateShort($payment->createdAt)) ?>
                                <?php if ($payment->methodLabel !== null): ?>
                                    &middot; <?= $view->e($payment->methodLabel) ?>
                                <?php endif; ?>
                                <?php if ($payment->transferRef !== null): ?>
                                    &middot; <?= $view->e($payment->transferRef) ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($payment->isRejected() && $payment->rejectionReason !== null): ?>
                                <span class="mt-1 block text-xs font-semibold text-rose-600">
                                    <?= $view->t('payment.rejected_note', ['reason' => $payment->rejectionReason]) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= $view->e($payment->status->badgeClass()) ?>">
                            <?= $view->t($payment->status->translationKey()) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Cancellation -->
    <?php if ($canCancel): ?>
        <div class="no-print card mt-6 border-rose-100 p-6">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500">
                <?= $view->t('lookup.cancel') ?>
            </h2>
            <form method="post"
                  action="<?= $view->url('booking/' . $appointment->reference->value . '/cancel') ?>"
                  class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                  data-confirm="<?= $view->e($view->tRaw('lookup.cancel_confirm')) ?>">
                <?= $view->csrfField() ?>

                <?php /* The phone number is required again: the reference alone
                          arrives in a forwardable email, so it must not be
                          enough to cancel someone else's appointment. */ ?>
                <div class="field">
                    <label class="label" for="cancel_phone"><?= $view->t('lookup.phone') ?></label>
                    <input class="input" type="tel" id="cancel_phone" name="phone"
                           placeholder="<?= $view->e($view->tRaw('booking.phone_ph')) ?>" required>
                </div>

                <div class="field">
                    <label class="label" for="cancel_reason"><?= $view->t('email.label_reason') ?></label>
                    <input class="input" type="text" id="cancel_reason" name="reason">
                </div>

                <button type="submit" class="btn-danger btn-sm h-[42px]">
                    <?= $view->t('lookup.cancel') ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</section>
