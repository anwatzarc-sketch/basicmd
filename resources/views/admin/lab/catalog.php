<?php
/**
 * Master test directory.
 *
 * Grouped by panel rather than flattened into one long analyte list: a
 * reference interval only means anything next to the panel and specimen
 * it was set for, and "Sodium 135-145" floating free invites someone to
 * assume it applies to the urine sodium too.
 *
 * Inactive panels are listed, greyed, rather than hidden - a retired
 * panel still explains the reports already printed under it.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\LabPanel> $panels
 * @var bool $canManage
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h2 class="text-base font-extrabold text-medical-900">Master test directory</h2>
        <p class="mt-1 text-sm text-slate-500">
            Orderable panels, their analytes, units and reference intervals. These decide what every future
            report flags as abnormal.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= $view->adminUrl('lab') ?>" class="btn-secondary btn-sm">Back to queue</a>
        <?php if ($canManage): ?>
            <a href="<?= $view->adminUrl('lab/catalog/create') ?>" class="btn-primary btn-sm">New panel</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($panels === []): ?>
    <div class="empty-state mt-6">
        <p class="text-sm text-slate-500">
            No panels defined yet.
            <?php if ($canManage): ?>
                <a class="font-semibold text-medical-700 underline" href="<?= $view->adminUrl('lab/catalog/create') ?>">Add the first one</a>.
            <?php else: ?>
                An administrator has to add them before requisitions can be raised.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="mt-6 grid gap-5">
        <?php foreach ($panels as $panel): ?>
            <section class="card <?= $panel->isActive ? '' : 'opacity-60' ?>">
                <div class="flex flex-wrap items-start justify-between gap-3 p-5 pb-3">
                    <div>
                        <h3 class="text-sm font-extrabold text-medical-900">
                            <?= $view->e($panel->panelName) ?>
                            <span class="badge ml-2 font-mono"><?= $view->e($panel->panelCode) ?></span>
                            <?php if (!$panel->isActive): ?>
                                <span class="chip--absent ml-1">Retired</span>
                            <?php endif; ?>
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            <?= $view->e($panel->department) ?>
                            &middot; <?= $view->e($panel->specimenType) ?>
                            &middot; <?= (int) $panel->parameterCount() ?> parameter<?= $panel->parameterCount() === 1 ? '' : 's' ?>
                            <?php if ($panel->methodology !== null): ?>
                                &middot; <?= $view->e($panel->methodology) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($canManage): ?>
                        <div class="flex items-center gap-2">
                            <a href="<?= $view->adminUrl('lab/catalog/' . $panel->id . '/edit') ?>" class="btn-secondary btn-sm">Edit</a>
                            <form method="post" action="<?= $view->adminUrl('lab/catalog/' . $panel->id . '/delete') ?>"
                                  data-confirm="Requisitions already raised under this panel keep their results and stay printable. Only the directory entry is removed."
                                  data-confirm-title="Delete this panel?"
                                  data-confirm-action="Delete"
                                  data-confirm-variant="danger">
                                <?= $view->csrfField() ?>
                                <button type="submit" class="btn-ghost btn-sm text-rose-600">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($panel->parameters === []): ?>
                    <p class="px-5 pb-5 text-xs text-slate-500">No analytes defined - this panel cannot be resulted yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Units</th>
                                    <th>Reference interval</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($panel->parameters as $parameter): ?>
                                    <tr>
                                        <td class="font-semibold text-slate-900"><?= $view->e($parameter->parameterName) ?></td>
                                        <td class="font-mono text-xs text-slate-500"><?= $view->e($parameter->unit) ?></td>
                                        <td class="font-mono text-xs text-slate-700"><?= $view->e($parameter->referenceDisplay()) ?></td>
                                        <td class="text-xs text-slate-500"><?= $view->e($parameter->methodology) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
