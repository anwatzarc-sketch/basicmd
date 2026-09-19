<?php
/**
 * Facility management.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\Facility> $facilities
 * @var list<\MediCareMini\Domain\Enum\FacilityStatus> $statuses
 * @var array<string,int> $counts
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex flex-wrap gap-2">
        <?php foreach ($statuses as $status): ?>
            <span class="badge <?= $view->e($status->badgeClass()) ?>">
                <?= $view->e($status->label()) ?>: <?= (int) ($counts[$status->value] ?? 0) ?>
            </span>
        <?php endforeach; ?>
    </div>
    <a href="<?= $view->adminUrl('facilities/create') ?>" class="btn-primary btn-sm">Add facility</a>
</div>

<div class="card mt-6">
    <?php if ($facilities === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No facilities yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Facility</th><th>Type</th><th>Room</th><th>Status</th><th>Public</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($facilities as $facility): ?>
                        <tr>
                            <td>
                                <b class="block text-slate-900"><?= $view->e($facility->name) ?></b>
                                <?php if ($facility->notes !== null): ?>
                                    <span class="text-xs text-slate-500"><?= $view->excerpt($facility->notes, 60) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-slate-600"><?= $view->e($facility->type) ?></td>
                            <td class="text-xs text-slate-500"><?= $view->e($facility->roomLabel ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $view->e($facility->status->badgeClass()) ?>">
                                    <?= $view->e($facility->status->label()) ?>
                                </span>
                            </td>
                            <td class="text-xs font-semibold <?= $facility->showsPublicly() ? 'text-emerald-600' : 'text-slate-400' ?>">
                                <?= $facility->showsPublicly() ? 'Visible' : 'Hidden' ?>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="<?= $view->adminUrl('facilities/' . $facility->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>
                                    <form method="post" action="<?= $view->adminUrl('facilities/' . $facility->id . '/delete') ?>"
                                          data-confirm="Remove this facility?">
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
