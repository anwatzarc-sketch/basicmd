<?php
/**
 * Payment verification screen - the Finance officer's decision point.
 *
 * The uploaded receipt is shown inline, next to the amount the patient
 * claims to have sent and the amount actually owed, so the two can be
 * compared without leaving the page.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\Payment $payment
 * @var \MediCareMini\Domain\Entity\Appointment|null $appointment
 * @var list<array<string,mixed>> $duplicates
 * @var list<\MediCareMini\Domain\Entity\Payment> $otherPayments
 * @var list<array<string,mixed>> $history
 * @var bool $canVerify
 */

declare(strict_types=1);

$t         = $view->translator;
$proofUrl  = $view->adminUrl('payments/' . $payment->id . '/proof');
$amountDue = $appointment?->balanceDue() ?? $payment->amount;

/** Flags a mismatch between what was sent and what is owed. */
$amountMatches = $appointment !== null
    && $payment->amount->amountMinor === $appointment->totalAmount->amountMinor;
?>

<div class="grid gap-6 xl:grid-cols-[1fr_400px]">

    <!-- ---------------- Proof viewer ---------------- -->
    <div class="card-pad">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-extrabold text-medical-900">Uploaded receipt</h2>
            <?php if ($payment->hasProofFile()): ?>
                <a href="<?= $proofUrl ?>?download=1" class="btn-secondary btn-sm">Download</a>
            <?php endif; ?>
        </div>

        <?php if (!$payment->hasProofFile()): ?>
            <p class="mt-6 rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">
                No file was uploaded. <?= $view->e($payment->adminNote ?? '') ?>
            </p>
        <?php elseif ($payment->isImageProof()): ?>
            <?php /* Images render inline; the route is authenticated and sends
                      nosniff plus no-store, so this never leaks or caches. */ ?>
            <a href="<?= $proofUrl ?>" target="_blank" rel="noopener"
               class="mt-4 block overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <img src="<?= $proofUrl ?>" alt="Payment receipt"
                     class="mx-auto max-h-[560px] w-auto object-contain">
            </a>
        <?php elseif ($payment->isPdfProof()): ?>
            <object data="<?= $proofUrl ?>" type="application/pdf"
                    class="mt-4 h-[560px] w-full rounded-2xl border border-slate-200">
                <p class="p-6 text-sm text-slate-500">
                    Your browser cannot display this PDF.
                    <a class="font-bold text-medical-700 hover:underline" href="<?= $proofUrl ?>?download=1">Download it instead</a>.
                </p>
            </object>
        <?php endif; ?>

        <?php if ($payment->proofOriginalName !== null): ?>
            <p class="mt-3 text-xs text-slate-500">
                <?= $view->e($payment->proofOriginalName) ?>
                &middot; <?= $view->e($payment->proofSizeLabel()) ?>
                &middot; <span class="font-mono"><?= $view->e($payment->shortHash()) ?></span>
            </p>
        <?php endif; ?>
    </div>

    <!-- ---------------- Decision panel ---------------- -->
    <aside class="grid gap-6">

        <?php if ($duplicates !== []): ?>
            <?php /* The single most important warning on this screen: the same
                      file has already been submitted against another booking. */ ?>
            <div class="alert-error" role="alert">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span>
                    <b class="block">Duplicate receipt detected</b>
                    This exact file was already submitted on
                    <?= count($duplicates) ?> other booking<?= count($duplicates) === 1 ? '' : 's' ?>:
                    <?php foreach ($duplicates as $duplicate): ?>
                        <a class="font-mono underline" href="<?= $view->adminUrl('appointments/' . (int) $duplicate['appointment_id']) ?>">
                            <?= $view->e($duplicate['booking_ref']) ?>
                        </a>
                    <?php endforeach; ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Payment details</h2>

            <dl class="mt-4 grid gap-3 text-sm">
                <?php
                $rows = [
                    ['Booking',        $payment->bookingRef ?? '—'],
                    ['Patient',        $payment->patientName ?? '—'],
                    ['Method',         $payment->methodLabel ?? '—'],
                    ['Kind',           $payment->kind->label()],
                    ['Transaction no', $payment->transferRef ?? '—'],
                    ['Payer name',     $payment->payerName ?? '—'],
                    ['Transfer date',  $payment->transferredAt !== null ? $t->dateShort($payment->transferredAt) : '—'],
                    ['Submitted',      $t->dateTime($payment->createdAt)],
                ];
                foreach ($rows as [$label, $value]):
                ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500"><?= $view->e($label) ?></dt>
                        <dd class="text-right font-semibold text-slate-900"><?= $view->e($value) ?></dd>
                    </div>
                <?php endforeach; ?>

                <div class="mt-2 flex justify-between gap-3 border-t border-slate-200 pt-3">
                    <dt class="font-bold text-slate-700">Amount claimed</dt>
                    <dd class="text-lg font-extrabold text-medical-700"><?= $view->e($t->money($payment->amount)) ?></dd>
                </div>

                <?php if ($appointment !== null): ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Booking total</dt>
                        <dd class="font-bold <?= $amountMatches ? 'text-emerald-700' : 'text-amber-700' ?>">
                            <?= $view->e($t->money($appointment->totalAmount)) ?>
                            <?php if (!$amountMatches): ?>
                                <span class="block text-[11px] font-semibold text-amber-600">Amounts differ</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>

            <div class="mt-5 border-t border-slate-200 pt-4">
                <span class="badge <?= $view->e($payment->status->badgeClass()) ?>">
                    <?= $view->e($payment->status->label()) ?>
                </span>
                <?php if ($payment->verifierName !== null): ?>
                    <span class="ml-2 text-xs text-slate-500">
                        by <?= $view->e($payment->verifierName) ?>
                        <?php if ($payment->verifiedAt !== null): ?>
                            &middot; <?= $view->e($t->relative($payment->verifiedAt)) ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($appointment !== null): ?>
                <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>" class="btn-secondary btn-sm mt-4 w-full">
                    Open appointment
                </a>
            <?php endif; ?>
        </div>

        <?php if ($canVerify && $payment->isPendingReview()): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Decision</h2>

                <?php
                // Spell out the amount and the booking: verification credits
                // real money against a real appointment, and a duplicate
                // receipt is exactly the case a rushed click would wave through.
                $verifyMessage = sprintf(
                    'This credits %s to booking %s and emails the patient to say their payment is confirmed.',
                    $t->money($payment->amount),
                    $payment->bookingRef ?? '',
                );

                if ($duplicates !== []) {
                    $verifyMessage .= sprintf(
                        ' Warning: this receipt has already been submitted on %d other booking%s.',
                        count($duplicates),
                        count($duplicates) === 1 ? '' : 's',
                    );
                }
                ?>
                <form method="post" action="<?= $view->adminUrl('payments/' . $payment->id . '/verify') ?>"
                      class="mt-4"
                      data-confirm="<?= $view->e($verifyMessage) ?>"
                      data-confirm-title="Verify payment"
                      data-confirm-action="Verify &amp; notify"
                      data-confirm-variant="<?= $duplicates !== [] ? 'danger' : 'primary' ?>">
                    <?= $view->csrfField() ?>
                    <label class="label" for="admin_note">Internal note (optional)</label>
                    <input class="input" type="text" id="admin_note" name="admin_note"
                           placeholder="Not shown to the patient">
                    <button type="submit" class="btn-primary mt-3 w-full">Verify payment</button>
                </form>

                <form method="post" action="<?= $view->adminUrl('payments/' . $payment->id . '/reject') ?>"
                      class="mt-6 border-t border-slate-200 pt-5">
                    <?= $view->csrfField() ?>
                    <label class="label" for="rejection_reason">
                        Rejection reason <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <textarea class="textarea <?= isset($errors['rejection_reason']) ? 'input-error' : '' ?>"
                              id="rejection_reason" name="rejection_reason" rows="3" required
                              placeholder="The patient will see this, so explain what to fix"></textarea>
                    <?php if (isset($errors['rejection_reason'])): ?>
                        <span class="field-error"><?= $view->e($errors['rejection_reason']) ?></span>
                    <?php endif; ?>
                    <button type="submit" class="btn-danger mt-3 w-full">Reject payment</button>
                </form>
            </div>
        <?php elseif ($payment->status->isTerminal()): ?>
            <div class="alert-info" role="status">
                <span>This payment was already <?= $view->e(strtolower($payment->status->label())) ?> and cannot be changed.</span>
            </div>
        <?php endif; ?>

        <?php if ($otherPayments !== [] && count($otherPayments) > 1): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Other payments on this booking</h2>
                <ul class="mt-4 grid gap-2.5">
                    <?php foreach ($otherPayments as $other): ?>
                        <?php if ($other->id === $payment->id) { continue; } ?>
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <a href="<?= $view->adminUrl('payments/' . $other->id) ?>"
                               class="font-semibold text-medical-700 hover:underline">
                                <?= $view->e($t->money($other->amount)) ?>
                            </a>
                            <span class="badge <?= $view->e($other->status->badgeClass()) ?>">
                                <?= $view->e($other->status->label()) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </aside>
</div>
