<?php
/**
 * Appointment detail, with the lifecycle state machine rendered as buttons.
 *
 * Only transitions the domain actually permits are offered; there is no way
 * to construct an illegal state change from the UI, and the service re-checks
 * it under a row lock anyway.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Appointment $appointment
 * @var list<\Aster\Domain\Entity\Payment> $payments
 * @var list<array<string,mixed>> $history
 * @var list<\Aster\Domain\Enum\AppointmentStatus> $transitions
 * @var bool $canEdit
 * @var \Aster\Domain\Entity\Encounter|null $encounter Phase II check-in bridge; null until checked in.
 */

declare(strict_types=1);

use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\Gender;

$t         = $view->translator;
$adminPath = $view->config->adminPath;
$ethiopian = $appointment->ethiopianDate();
?>

<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div class="grid gap-6">
        <!-- ---------------- Summary ---------------- -->
        <div class="card-pad">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="font-mono text-xs font-bold tracking-wider text-medical-600">
                        <?= $view->e($appointment->reference->value) ?>
                    </span>
                    <h2 class="mt-1 text-2xl font-extrabold text-medical-900"><?= $view->e($appointment->patientName) ?></h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Booked <?= $view->e($t->relative($appointment->createdAt)) ?>
                        via <?= $view->e($appointment->source->label()) ?>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge <?= $view->e($appointment->status->badgeClass()) ?>">
                        <?= $view->e($appointment->status->label()) ?>
                    </span>
                    <?php if ($appointment->totalAmount->isPositive()): ?>
                        <span class="badge <?= $view->e($appointment->paymentStatus->badgeClass()) ?>">
                            <?= $view->e($appointment->paymentStatus->label()) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($appointment->isExpress()): ?>
                        <span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?>">Express</span>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="mt-7 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php
                $facts = [
                    ['Phone',   $appointment->patientPhone->formatNational()],
                    ['Email',   $appointment->patientEmail ?? '—'],
                    ['Service', $appointment->subjectLabel()],
                    ['Doctor',  $appointment->doctorLabel()],
                    ['Date',    $t->dateShort($appointment->date) . '  (' . $ethiopian->format('en') . ')'],
                    ['Time',    $appointment->timeSlot->label()],
                ];
                foreach ($facts as [$label, $value]):
                ?>
                    <div>
                        <dt class="stat-label"><?= $view->e($label) ?></dt>
                        <dd class="mt-1 font-semibold text-slate-900"><?= $view->e($value) ?></dd>
                    </div>
                <?php endforeach; ?>

                <?php if ($appointment->totalAmount->isPositive()): ?>
                    <div>
                        <dt class="stat-label">Total</dt>
                        <dd class="mt-1 font-extrabold text-medical-700"><?= $view->e($t->money($appointment->totalAmount)) ?></dd>
                    </div>
                    <div>
                        <dt class="stat-label">Paid</dt>
                        <dd class="mt-1 font-semibold text-emerald-700"><?= $view->e($t->money($appointment->amountPaid)) ?></dd>
                    </div>
                    <div>
                        <dt class="stat-label">Balance</dt>
                        <dd class="mt-1 font-semibold <?= $appointment->balanceDue()->isPositive() ? 'text-rose-600' : 'text-slate-400' ?>">
                            <?= $view->e($t->money($appointment->balanceDue())) ?>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($appointment->cancelReason !== null): ?>
                <div class="alert-warning mt-6" role="note">
                    <span><b>Cancellation reason:</b> <?= $view->e($appointment->cancelReason) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- ---------------- Clinical notes ---------------- -->
        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Clinical notes</h2>
            <p class="mt-1 text-xs text-slate-500">
                Patient-reported symptoms and staff notes. Not shown to the patient.
            </p>

            <form method="post" action="<?= $view->adminUrl('appointments/' . $appointment->id . '/notes') ?>" class="mt-4">
                <?= $view->csrfField() ?>
                <textarea class="textarea" name="patient_notes" rows="5"
                          <?= $view->attr(!$canEdit, 'readonly') ?>><?= $view->e($appointment->patientNotes ?? '') ?></textarea>
                <?php if ($canEdit): ?>
                    <button type="submit" class="btn-secondary btn-sm mt-3">Save notes</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- ---------------- Payments ---------------- -->
        <?php if ($payments !== []): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Payments</h2>
                <ul class="mt-4 grid gap-3">
                    <?php foreach ($payments as $payment): ?>
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4">
                            <div class="min-w-0">
                                <b class="text-slate-900"><?= $view->e($t->money($payment->amount)) ?></b>
                                <span class="ml-2 text-xs text-slate-500">
                                    <?= $view->e($payment->kind->label()) ?>
                                    <?php if ($payment->methodLabel !== null): ?>
                                        &middot; <?= $view->e($payment->methodLabel) ?>
                                    <?php endif; ?>
                                    <?php if ($payment->transferRef !== null): ?>
                                        &middot; <?= $view->e($payment->transferRef) ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ($payment->rejectionReason !== null): ?>
                                    <span class="mt-1 block text-xs font-semibold text-rose-600">
                                        <?= $view->e($payment->rejectionReason) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="badge <?= $view->e($payment->status->badgeClass()) ?>">
                                    <?= $view->e($payment->status->label()) ?>
                                </span>
                                <a href="<?= $view->adminUrl('payments/' . $payment->id) ?>" class="btn-ghost btn-sm">Review</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- ---------------- Sidebar: actions + history ---------------- -->
    <aside class="grid gap-6">
        <?php if ($encounter !== null): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Checked in</h2>
                <p class="mt-2 font-mono text-sm text-medical-700"><?= $view->e($encounter->patientVisitNumber->value) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= $view->e($encounter->status->label()) ?></p>
            </div>
        <?php elseif ($canEdit && $appointment->status === AppointmentStatus::CONFIRMED): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Check in</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Starts a clinical encounter for this visit. These two details are not asked
                    at booking time and are needed for the patient record.
                </p>

                <form method="post" action="<?= $view->adminUrl('appointments/' . $appointment->id . '/check-in') ?>"
                      class="mt-4 grid gap-3">
                    <?= $view->csrfField() ?>

                    <div class="field">
                        <label class="label" for="date_of_birth">Date of birth</label>
                        <input class="input" type="date" id="date_of_birth" name="date_of_birth" required
                               max="<?= (new DateTimeImmutable())->format('Y-m-d') ?>">
                    </div>

                    <div class="field">
                        <label class="label" for="gender">Gender</label>
                        <select class="select" id="gender" name="gender" required>
                            <option value="">Select...</option>
                            <?php foreach (Gender::all() as $gender): ?>
                                <option value="<?= $view->e($gender->value) ?>"><?= $view->e($gender->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary btn-sm w-full">Check in</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($canEdit && $transitions !== []): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Actions</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Only the transitions allowed from <b><?= $view->e($appointment->status->label()) ?></b> are shown.
                </p>

                <div class="mt-4 grid gap-3">
                    <?php foreach ($transitions as $target): ?>
                        <?php
                        // Cancelling opens no dialog: the form below already
                        // demands a reason, which is a deliberate enough step.
                        $confirmAttrs = '';

                        if ($target !== AppointmentStatus::CANCELLED) {
                            $isConfirming = $target === AppointmentStatus::CONFIRMED;

                            $confirmAttrs = sprintf(
                                ' data-confirm="%s" data-confirm-title="%s" data-confirm-action="%s" data-confirm-variant="%s"',
                                $view->e($isConfirming
                                    ? sprintf(
                                        'The patient will be emailed to say their appointment on %s at %s is confirmed.',
                                        $t->date($appointment->date),
                                        $appointment->timeSlot->label(),
                                    )
                                    : sprintf('Mark this appointment as %s? This cannot be undone.', $target->label())),
                                $view->e($isConfirming ? 'Confirm appointment' : $target->label()),
                                $view->e($isConfirming ? 'Confirm & notify' : 'Mark ' . $target->label()),
                                $target === AppointmentStatus::NO_SHOW ? 'danger' : 'primary',
                            );
                        }
                        ?>
                        <form method="post"
                              action="<?= $view->adminUrl('appointments/' . $appointment->id . '/status') ?>"
                              <?= $confirmAttrs ?>>
                            <?= $view->csrfField() ?>
                            <input type="hidden" name="status" value="<?= $view->e($target->value) ?>">

                            <?php if ($target === AppointmentStatus::CANCELLED): ?>
                                <?php /* A reason is mandatory - the patient sees it in the email. */ ?>
                                <label class="label" for="reason">Cancellation reason</label>
                                <input class="input mb-2" type="text" id="reason" name="reason" required
                                       placeholder="The patient will see this">
                            <?php endif; ?>

                            <button type="submit"
                                    class="<?= $target === AppointmentStatus::CANCELLED ? 'btn-danger' : 'btn-primary' ?> btn-sm w-full">
                                <?= $view->e($target === AppointmentStatus::CONFIRMED ? 'Confirm appointment' : 'Mark ' . $target->label()) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($history !== []): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">History</h2>
                <ol class="mt-4 grid gap-3">
                    <?php foreach ($history as $entry): ?>
                        <li class="border-l-2 border-slate-200 pl-3">
                            <span class="block text-xs font-bold text-slate-700"><?= $view->e($entry['summary'] ?? $entry['action']) ?></span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">
                                <?= $view->e($entry['user_name'] ?? $entry['actor_label'] ?? 'System') ?>
                                &middot; <?= $view->e($t->dateTime(new DateTimeImmutable($entry['created_at'] . ' UTC'))) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <a href="<?= $view->adminUrl('appointments') ?>" class="btn-secondary btn-sm">&larr; Back to list</a>
    </aside>
</div>
