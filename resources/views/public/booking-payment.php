<?php
/**
 * Payment instructions and proof upload.
 *
 * This page is the whole payment integration: show the patient where to send
 * the money, then take a photo of the receipt back.
 *
 * @var \Aster\Presentation\View\View            $view
 * @var \Aster\Domain\Entity\Appointment         $appointment
 * @var list<\Aster\Domain\Entity\PaymentMethod> $paymentMethods
 * @var list<\Aster\Domain\Entity\Payment>       $payments
 * @var bool                                     $canSubmit
 */

declare(strict_types=1);

$t       = $view->translator;
$maxMb   = (int) ($settings->int('proof_max_mb', 5));
$balance = $appointment->balanceDue();

/** The deposit applies only to packages; everything else is paid in full. */
$amountDue = $balance->isPositive() ? $balance : $appointment->totalAmount;

$latest = $payments[0] ?? null;
?>

<section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

    <?= $view->partial('partials/booking-steps', ['view' => $view, 'current' => 2]) ?>

    <div class="grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">

        <!-- ------------------------- Left: instructions ------------------------- -->
        <div>
            <h1 class="page-title"><?= $view->t('payment.title') ?></h1>
            <p class="mt-3 leading-relaxed text-slate-600 dark:text-slate-300"><?= $view->t('payment.lead') ?></p>

            <!-- The reference the patient must quote on the transfer. -->
            <div class="reference-box mt-6">
                <span class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-medical-700 dark:text-medical-300">
                    <?= $view->t('confirmation.reference') ?>
                </span>
                <div class="mt-2 font-mono text-2xl font-extrabold tracking-wider text-medical-900 dark:text-medical-100 sm:text-3xl">
                    <?= $view->e($appointment->reference->formatted()) ?>
                </div>
                <button type="button" class="chip mt-3"
                        data-copy="<?= $view->e($appointment->reference->value) ?>"
                        data-copied-label="<?= $view->e($view->tRaw('payment.copied')) ?>">
                    <?= $view->t('payment.copy') ?>
                </button>
            </div>

            <div class="alert-warning mt-5">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span><?= $view->t('payment.reference_note', ['ref' => $appointment->reference->value]) ?></span>
            </div>

            <?php if ($latest !== null && $latest->isRejected()): ?>
                <div class="alert-error mt-5" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>
                        <b class="block"><?= $view->t('payment.status_rejected') ?></b>
                        <?= $view->t('payment.rejected_note', ['reason' => $latest->rejectionReason ?? '']) ?>
                    </span>
                </div>
            <?php elseif ($latest !== null && $latest->isPendingReview()): ?>
                <div class="alert-info mt-5" role="status">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>
                        <b class="block"><?= $view->t('payment.submitted_title') ?></b>
                        <?= $view->t('payment.submitted_body') ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($canSubmit): ?>
            <form method="post"
                  action="<?= $view->url('booking/' . $appointment->reference->value . '/pay') ?>"
                  enctype="multipart/form-data"
                  class="card mt-8 p-6"
                  data-payment-form>
                <?= $view->csrfField() ?>

                <!-- ---------- Method selection ---------- -->
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-medical-700 dark:text-medical-300">
                    <?= $view->t('payment.choose_method') ?>
                </h2>

                <div class="mt-4 grid gap-3">
                    <?php foreach ($paymentMethods as $index => $method): ?>
                        <label class="radio-card">
                            <input type="radio" name="payment_method_id" value="<?= $method->id ?>"
                                   class="radio mt-1"
                                   data-requires-proof="<?= $method->expectsProof() ? '1' : '0' ?>"
                                   <?= $view->attr($index === 0, 'checked') ?>>
                            <span class="flex-1">
                                <span class="block text-sm font-bold text-medical-900 dark:text-medical-100"><?= $view->e($method->name($locale)) ?></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?= $view->t($method->channel->translationKey()) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- ---------- Per-method instructions ---------- -->
                <?php foreach ($paymentMethods as $method): ?>
                    <div class="soft-card mt-5"
                         data-method-panel="<?= $method->id ?>" hidden>

                        <?php if ($method->hasAccountDetails()): ?>
                            <dl class="grid gap-3 text-sm">
                                <?php if ($method->accountName !== null): ?>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('payment.account_name') ?></dt>
                                        <dd class="font-bold text-slate-900 dark:text-slate-100"><?= $view->e($method->accountName) ?></dd>
                                    </div>
                                <?php endif; ?>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('payment.account_number') ?></dt>
                                    <dd class="flex items-center gap-2">
                                        <span class="font-mono text-base font-extrabold tracking-wide text-medical-800 dark:text-medical-200">
                                            <?= $view->e($method->accountNumber ?? '') ?>
                                        </span>
                                        <button type="button" class="chip"
                                                data-copy="<?= $view->e($method->accountNumber ?? '') ?>"
                                                data-copied-label="<?= $view->e($view->tRaw('payment.copied')) ?>">
                                            <?= $view->t('payment.copy') ?>
                                        </button>
                                    </dd>
                                </div>

                                <?php if ($method->branch !== null): ?>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('payment.branch') ?></dt>
                                        <dd class="font-semibold text-slate-700 dark:text-slate-300"><?= $view->e($method->branch) ?></dd>
                                    </div>
                                <?php endif; ?>

                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                                    <dt class="font-bold text-slate-700 dark:text-slate-300"><?= $view->t('payment.amount_due') ?></dt>
                                    <dd class="text-lg font-extrabold text-medical-700 dark:text-medical-300"><?= $view->e($t->money($amountDue)) ?></dd>
                                </div>
                            </dl>
                        <?php endif; ?>

                        <?php if ($method->howTo($locale) !== null): ?>
                            <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                <?= $view->e($method->howTo($locale)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- ---------- Proof upload ---------- -->
                <div data-upload-block>
                    <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-700">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-medical-700 dark:text-medical-300">
                            <?= $view->t('payment.upload_title') ?>
                        </h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= $view->t('payment.upload_lead') ?></p>

                        <div class="mt-5 grid gap-5">
                            <div class="field">
                                <label class="label" for="proof">
                                    <?= $view->t('payment.upload_field') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>
                                <input class="input <?= isset($errors['proof']) ? 'input-error' : '' ?>"
                                       type="file" id="proof" name="proof"
                                       accept="image/jpeg,image/png,image/webp,application/pdf"
                                       data-proof-input data-proof-required data-max-mb="<?= $maxMb ?>">
                                <span class="hint"><?= $view->t('payment.upload_hint', ['size' => $maxMb]) ?></span>
                                <?php if (isset($errors['proof'])): ?>
                                    <span class="field-error"><?= $view->e($errors['proof']) ?></span>
                                <?php endif; ?>

                                <div class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs dark:border-slate-700 dark:bg-slate-800/60"
                                     data-proof-preview hidden>
                                    <svg class="h-5 w-5 shrink-0 text-medical-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span class="flex-1 truncate font-semibold text-slate-700 dark:text-slate-200" data-proof-name></span>
                                    <span class="text-slate-500 dark:text-slate-400" data-proof-size></span>
                                    <span class="font-bold text-rose-600" data-proof-warning hidden>
                                        <?= $view->t('validation.file_size', ['size' => $maxMb]) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="field">
                                    <label class="label" for="amount">
                                        <?= $view->t('payment.amount_sent') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>
                                    <input class="input <?= isset($errors['amount']) ? 'input-error' : '' ?>"
                                           type="number" id="amount" name="amount" step="0.01" min="1"
                                           inputmode="decimal"
                                           value="<?= $view->e($old['amount'] ?? $amountDue->toDatabase()) ?>"
                                           data-proof-required>
                                    <?php if (isset($errors['amount'])): ?>
                                        <span class="field-error"><?= $view->e($errors['amount']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="label" for="transfer_ref">
                                        <?= $view->t('payment.transfer_ref') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                                    </label>
                                    <input class="input <?= isset($errors['transfer_ref']) ? 'input-error' : '' ?>"
                                           type="text" id="transfer_ref" name="transfer_ref"
                                           value="<?= $view->e($old['transfer_ref'] ?? '') ?>"
                                           data-proof-required>
                                    <span class="hint"><?= $view->t('payment.transfer_ref_hint') ?></span>
                                    <?php if (isset($errors['transfer_ref'])): ?>
                                        <span class="field-error"><?= $view->e($errors['transfer_ref']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div class="field">
                                    <label class="label" for="payer_name"><?= $view->t('payment.payer_name') ?></label>
                                    <input class="input" type="text" id="payer_name" name="payer_name"
                                           value="<?= $view->e($old['payer_name'] ?? $appointment->patientName) ?>">
                                </div>

                                <div class="field">
                                    <label class="label" for="transferred_at"><?= $view->t('payment.transfer_date') ?></label>
                                    <input class="input" type="date" id="transferred_at" name="transferred_at"
                                           max="<?= date('Y-m-d') ?>"
                                           value="<?= $view->e($old['transferred_at'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary mt-8 w-full py-4 text-base">
                    <?= $view->t('payment.submit') ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- ------------------------- Right: summary ------------------------- -->
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="card-pad">
                <h2 class="panel-title">
                    <?= $view->t('booking.summary') ?>
                </h2>

                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_service') ?></dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-slate-100"><?= $view->e($appointment->subjectLabel()) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_doctor') ?></dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-slate-100"><?= $view->e($appointment->doctorLabel()) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_date') ?></dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-slate-100"><?= $view->e($t->date($appointment->date)) ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_time') ?></dt>
                        <dd class="text-right font-semibold text-slate-900 dark:text-slate-100"><?= $view->e($appointment->timeSlot->label()) ?></dd>
                    </div>

                    <?php if ($appointment->isExpress()): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('booking.tier') ?></dt>
                            <dd><span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?>">
                                <?= $view->t('queue_tier.express') ?>
                            </span></dd>
                        </div>
                    <?php endif; ?>

                    <div class="mt-2 flex justify-between gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                        <dt class="font-bold text-slate-700 dark:text-slate-300"><?= $view->t('booking.total') ?></dt>
                        <dd class="font-extrabold text-medical-700 dark:text-medical-300"><?= $view->e($t->money($appointment->totalAmount)) ?></dd>
                    </div>

                    <?php if ($appointment->amountPaid->isPositive()): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_paid') ?></dt>
                            <dd class="font-semibold text-emerald-700 dark:text-emerald-400"><?= $view->e($t->money($appointment->amountPaid)) ?></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400"><?= $view->t('email.label_balance') ?></dt>
                            <dd class="font-bold text-amber-700 dark:text-amber-400"><?= $view->e($t->money($balance)) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <span class="badge <?= $view->e($appointment->paymentStatus->badgeClass()) ?>">
                        <?= $view->t($appointment->paymentStatus->translationKey()) ?>
                    </span>
                </div>

                <a href="<?= $view->url('booking/' . $appointment->reference->value) ?>"
                   class="btn-secondary mt-5 w-full">
                    <?= $view->t('email.view_booking') ?>
                </a>
            </div>

            <?php if ($payments !== []): ?>
                <div class="card-pad mt-5">
                    <h2 class="panel-title">
                        <?= $view->t('email.label_status') ?>
                    </h2>
                    <ul class="mt-4 grid gap-3">
                        <?php foreach ($payments as $payment): ?>
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <div>
                                    <strong class="block text-slate-900 dark:text-slate-100"><?= $view->e($t->money($payment->amount)) ?></strong>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        <?= $view->e($t->dateShort($payment->createdAt)) ?>
                                        <?php if ($payment->methodLabel !== null): ?>
                                            &middot; <?= $view->e($payment->methodLabel) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="badge <?= $view->e($payment->status->badgeClass()) ?>">
                                    <?= $view->t($payment->status->translationKey()) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
