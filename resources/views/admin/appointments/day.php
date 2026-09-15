<?php
/**
 * Day sheet - the front desk's working view, grouped by time slot.
 *
 * Express bookings sort to the top of each slot: that priority is exactly
 * what the surcharge buys.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\Appointment> $appointments
 * @var DateTimeImmutable $date
 * @var list<\Aster\Domain\Enum\TimeSlot> $slots
 * @var array<string,int> $availability
 */

declare(strict_types=1);

$t = $view->translator;

$grouped = [];
foreach ($appointments as $appointment) {
    $grouped[$appointment->timeSlot->value][] = $appointment;
}
?>
<form method="get" class="card-pad flex flex-wrap items-end gap-3" data-auto-filter>
    <div class="field">
        <label class="label" for="date">Date</label>
        <input class="input" type="date" id="date" name="date" value="<?= $view->e($date->format('Y-m-d')) ?>">
    </div>
    <a href="<?= $view->adminUrl('appointments/day') ?>" class="btn-secondary btn-sm">Today</a>
    <span class="ml-auto text-sm font-bold text-slate-600"><?= count($appointments) ?> booked</span>
</form>

<div class="mt-6 grid gap-5">
    <?php foreach ($slots as $slot): ?>
        <?php $rows = $grouped[$slot->value] ?? []; ?>
        <section class="card">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-extrabold text-medical-900"><?= $view->e($slot->label()) ?></h2>
                <div class="flex items-center gap-3 text-xs font-bold">
                    <span class="text-slate-500"><?= count($rows) ?> booked</span>
                    <span class="badge <?= ($availability[$slot->value] ?? 0) > 0
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
                        <?= (int) ($availability[$slot->value] ?? 0) ?> free
                    </span>
                </div>
            </header>

            <?php if ($rows === []): ?>
                <p class="px-5 py-8 text-center text-sm text-slate-400">No appointments in this slot.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($rows as $appointment): ?>
                        <li class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-slate-900"><?= $view->e($appointment->patientName) ?></b>
                                    <?php if ($appointment->isExpress()): ?>
                                        <span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?>">Express</span>
                                    <?php endif; ?>
                                </div>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    <?= $view->e($appointment->patientPhone->formatNational()) ?>
                                    &middot; <?= $view->e($appointment->subjectLabel()) ?>
                                    &middot; <?= $view->e($appointment->doctorLabel()) ?>
                                </span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="badge <?= $view->e($appointment->status->badgeClass()) ?>">
                                    <?= $view->e($appointment->status->label()) ?>
                                </span>
                                <?php if ($appointment->totalAmount->isPositive()): ?>
                                    <span class="badge <?= $view->e($appointment->paymentStatus->badgeClass()) ?>">
                                        <?= $view->e($appointment->paymentStatus->label()) ?>
                                    </span>
                                <?php endif; ?>
                                <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>" class="btn-ghost btn-sm">Open</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
