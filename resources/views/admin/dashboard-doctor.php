<?php
/**
 * Clinical day view - what a doctor sees on sign-in.
 *
 * Deliberately narrow: their own queue for today, nothing financial and no
 * other clinician's patients.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Doctor|null $doctor
 * @var list<\Aster\Domain\Entity\Appointment> $queue
 * @var DateTimeImmutable $date
 */

declare(strict_types=1);

$t = $view->translator;
?>
<?php if ($doctor === null): ?>
    <div class="alert-warning" role="alert">
        <span>
            Your account is not linked to a doctor profile yet, so no appointments can be shown.
            Ask an administrator to link it from Staff Accounts.
        </span>
    </div>
<?php else: ?>
    <div class="card-pad">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-medical-900"><?= $view->e($doctor->fullName) ?></h2>
                <p class="mt-1 text-sm text-slate-500">
                    <?= $view->e($doctor->specialty) ?> &middot; <?= $view->e($t->date($date, true)) ?>
                </p>
            </div>
            <div class="text-right">
                <span class="stat-label">Booked today</span>
                <b class="block text-2xl font-extrabold text-medical-800">
                    <?= count($queue) ?>/<?= $doctor->dailyCapacity ?>
                </b>
            </div>
        </div>
    </div>

    <div class="card mt-6">
        <?php if ($queue === []): ?>
            <p class="p-10 text-center text-sm text-slate-500">No appointments scheduled for today.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($queue as $appointment): ?>
                    <li class="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="text-slate-900"><?= $view->e($appointment->patientName) ?></b>
                                <?php if ($appointment->isExpress()): ?>
                                    <span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?>">Express</span>
                                <?php endif; ?>
                            </div>
                            <span class="mt-1 block text-xs text-slate-500">
                                <?= $view->e($appointment->timeSlot->label()) ?>
                                &middot; <?= $view->e($appointment->subjectLabel()) ?>
                            </span>
                            <?php if ($appointment->patientNotes !== null): ?>
                                <p class="mt-2 max-w-prose rounded-lg bg-slate-50 p-2.5 text-xs leading-relaxed text-slate-600">
                                    <?= $view->e($appointment->patientNotes) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="badge <?= $view->e($appointment->status->badgeClass()) ?>">
                                <?= $view->e($appointment->status->label()) ?>
                            </span>
                            <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>" class="btn-ghost btn-sm">Open</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>
