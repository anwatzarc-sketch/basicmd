<?php
/**
 * Patient detail: identity, allergies (decrypted here only, for an
 * authorised view of exactly this one record), and encounter history.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Patient $patient
 * @var list<string> $allergies
 * @var list<\Aster\Domain\Entity\Encounter> $encounters
 */

declare(strict_types=1);

use Aster\Domain\Enum\EncounterStatus;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div class="grid gap-6">
        <div class="card-pad">
            <p class="eyebrow">Patient</p>
            <h2 class="mt-1 text-2xl font-extrabold text-medical-900"><?= $view->e($patient->fullName()) ?></h2>
            <p class="mt-1 font-mono text-sm text-medical-700"><?= $view->e($patient->pid->value) ?></p>

            <dl class="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="stat-label">Date of birth</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->dateOfBirth->format('Y-m-d')) ?> (<?= $patient->age() ?>y)</dd></div>
                <div><dt class="stat-label">Gender</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->gender->label()) ?></dd></div>
                <div><dt class="stat-label">Blood group</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->bloodGroup?->value ?? 'Unknown') ?></dd></div>
                <div><dt class="stat-label">Phone</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->phoneNumber->e164) ?></dd></div>
                <div><dt class="stat-label">Email</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->email ?? '-') ?></dd></div>
                <div><dt class="stat-label">National ID</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->nationalId ?? '-') ?></dd></div>
            </dl>

            <?php if ($allergies !== []): ?>
                <div class="mt-6">
                    <b class="text-xs font-extrabold uppercase tracking-wider text-rose-600">Allergies</b>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach ($allergies as $allergy): ?>
                            <span class="badge border-rose-200 bg-rose-50 text-rose-700"><?= $view->e($allergy) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Encounters</h2>
            <?php if ($encounters === []): ?>
                <p class="mt-3 text-sm text-slate-500">No encounters recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th>Visit</th><th>Type</th><th>Status</th><th>Started</th></tr></thead>
                        <tbody>
                            <?php foreach ($encounters as $encounter): ?>
                                <tr>
                                    <td class="font-mono text-xs"><?= $view->e($encounter->patientVisitNumber->value) ?></td>
                                    <td><?= $view->e($encounter->visitType->label()) ?></td>
                                    <td><span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span></td>
                                    <td class="text-slate-500"><?= $view->e($encounter->createdAt->format('Y-m-d H:i')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="grid gap-6">
        <a href="<?= $view->adminUrl('patients') ?>" class="btn-secondary btn-sm">&larr; Back to search</a>
    </aside>
</div>
