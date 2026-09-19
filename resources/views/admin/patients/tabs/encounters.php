<?php
/**
 * Patient Detail - Encounters tab: full history (spec §4.3, receptionist's
 * check-in/registration context).
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\Patient $patient
 * @var list<\MediCareMini\Domain\Entity\Encounter> $encounters
 */

declare(strict_types=1);

$adminPath = '/' . $view->config->adminPath;
?>
<h2 class="text-base font-extrabold text-medical-900">Encounter history</h2>

<?php if ($encounters === []): ?>
    <p class="mt-3 text-sm text-slate-500">No encounters recorded yet.</p>
<?php else: ?>
    <div class="table-wrap mt-4">
        <table class="table">
            <thead><tr><th>Visit</th><th>Type</th><th>Status</th><th>Location</th><th>Physician</th><th>Started</th></tr></thead>
            <tbody>
                <?php foreach ($encounters as $encounter): ?>
                    <tr>
                        <td class="font-mono text-xs">
                            <a href="<?= $view->e($adminPath . '/encounters/' . $encounter->id) ?>" class="text-medical-700 hover:underline">
                                <?= $view->e($encounter->patientVisitNumber->value) ?>
                            </a>
                        </td>
                        <td><?= $view->e($encounter->visitType->label()) ?></td>
                        <td><span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span></td>
                        <td class="text-slate-500"><?= $view->e($encounter->locationLabel ?? '-') ?></td>
                        <td class="text-slate-500"><?= $view->e($encounter->physicianName ?? '-') ?></td>
                        <td class="text-slate-500"><?= $view->e($encounter->createdAt->format('Y-m-d H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
