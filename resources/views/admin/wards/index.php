<?php
/**
 * Ward Locations admin list (spec §4.5). Occupancy is display-only here -
 * this screen never writes is_occupied (see WardLocationController's
 * docblock).
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\WardLocation> $wards
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <p class="text-sm text-slate-500">
        Ward scoping (Role Management's assign-to-user step) is keyed on ward name - register
        every bed a role should ever be scoped to here first.
    </p>
    <a href="<?= $view->adminUrl('wards/create') ?>" class="btn-primary btn-sm">Register bed</a>
</div>

<div class="card mt-6">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Ward</th><th>Room</th><th>Bed</th><th>Type</th><th>Occupancy</th><th>Daily rate</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($wards as $ward): ?>
                    <tr>
                        <td><b class="text-slate-900"><?= $view->e($ward->wardName) ?></b></td>
                        <td class="text-slate-600"><?= $view->e($ward->roomNumber) ?></td>
                        <td class="text-slate-600"><?= $view->e($ward->bedNumber) ?></td>
                        <td>
                            <?php if ($ward->isTransient): ?>
                                <span class="badge border-slate-200 bg-slate-50 text-slate-600">Transient</span>
                            <?php else: ?>
                                <span class="badge border-medical-200 bg-medical-50 text-medical-700">Admission bed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ward->isOccupied): ?>
                                <span class="badge border-amber-200 bg-amber-50 text-amber-700">Occupied</span>
                            <?php else: ?>
                                <span class="badge border-teal-200 bg-teal-50 text-teal-700">Available</span>
                            <?php endif; ?>
                            <span class="hint">Set by admission/discharge, not editable here.</span>
                        </td>
                        <td class="text-slate-600"><?= $view->e($ward->dailyRate->format()) ?></td>
                        <td class="text-right">
                            <a href="<?= $view->adminUrl('wards/' . $ward->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
