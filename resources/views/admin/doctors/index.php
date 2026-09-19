<?php
/**
 * Doctor directory.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\Doctor> $doctors
 * @var array $filters
 * @var list<\MediCareMini\Domain\Enum\DoctorStatus> $statuses
 */

declare(strict_types=1);

$t = $view->translator;
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3" data-auto-filter>
        <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" type="search" id="q" name="q" placeholder="Name, specialty or phone"
                   value="<?= $view->e($filters['q'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $view->e($status->value) ?>" <?= $view->attr(($filters['status'] ?? '') === $status->value, 'selected') ?>>
                        <?= $view->e($status->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <a href="<?= $view->adminUrl('doctors/create') ?>" class="btn-primary btn-sm">Add doctor</a>
</div>

<div class="card mt-6">
    <?php if ($doctors === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No doctors yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th><th>Specialty</th><th>Fee</th>
                        <th>Capacity</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-medical-100 text-xs font-bold text-medical-800">
                                        <?= $view->e($doctor->displayInitials()) ?>
                                    </span>
                                    <div class="min-w-0">
                                        <b class="block truncate text-slate-900"><?= $view->e($doctor->fullName) ?></b>
                                        <span class="text-xs text-slate-500"><?= $view->e($doctor->credentials ?? '') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-slate-600"><?= $view->e($doctor->specialty) ?></td>
                            <td class="text-slate-600">
                                <?= $doctor->consultationFee->isPositive() ? $view->e($t->money($doctor->consultationFee)) : '&mdash;' ?>
                            </td>
                            <td class="text-xs text-slate-500">
                                <?= $doctor->dailyCapacity ?>/day &middot; <?= $doctor->slotCapacity ?>/slot
                            </td>
                            <td><span class="badge <?= $view->e($doctor->status->badgeClass()) ?>"><?= $view->e($doctor->status->label()) ?></span></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="<?= $view->adminUrl('doctors/' . $doctor->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>
                                    <form method="post" action="<?= $view->adminUrl('doctors/' . $doctor->id . '/delete') ?>"
                                          data-confirm="Remove <?= $view->e($doctor->fullName) ?> from the directory? Their appointment history is kept.">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn-ghost btn-sm text-rose-600">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
