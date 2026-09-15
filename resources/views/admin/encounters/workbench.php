<?php
/**
 * Encounter workbench - default view: search by visit number, and today's
 * active encounters (FRS 10.2).
 *
 * @var \Aster\Presentation\View\View $view
 * @var string $searchTerm
 * @var bool $notFound
 * @var list<\Aster\Domain\Entity\Encounter> $active
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="field">
            <label class="label" for="visit">Visit number</label>
            <input class="input font-mono" type="text" id="visit" name="visit" value="<?= $view->e($searchTerm) ?>"
                   placeholder="VIS-20260915-00001" autofocus>
        </div>
        <button type="submit" class="btn-primary btn-sm">Open</button>
    </form>
    <a href="<?= $view->adminUrl('patients') ?>" class="btn-secondary btn-sm">Find a patient to start an encounter</a>
</div>

<?php if ($notFound): ?>
    <div class="alert-error mt-4" role="alert"><span class="flex-1">No encounter found for that visit number.</span></div>
<?php endif; ?>

<div class="card mt-6">
    <div class="card-pad !pb-0">
        <h2 class="text-base font-extrabold text-medical-900">Active encounters</h2>
    </div>
    <?php if ($active === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No active encounters right now.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Visit</th><th>Patient</th><th>Type</th><th>Status</th><th>Location</th><th>Physician</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($active as $encounter): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= $view->e($encounter->patientVisitNumber->value) ?></td>
                            <td class="font-semibold text-slate-900"><?= $view->e($encounter->patientName ?? '-') ?></td>
                            <td><?= $view->e($encounter->visitType->label()) ?></td>
                            <td><span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span></td>
                            <td class="text-slate-500"><?= $view->e($encounter->locationLabel ?? '-') ?></td>
                            <td class="text-slate-500"><?= $view->e($encounter->physicianName ?? '-') ?></td>
                            <td class="text-right">
                                <a href="<?= $view->adminUrl('encounters/workbench?visit=' . urlencode($encounter->patientVisitNumber->value)) ?>" class="btn-ghost btn-sm">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
