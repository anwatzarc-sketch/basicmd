<?php
/**
 * Patient Detail - the one canonical shell every role reaches (Patient
 * Aggregate spec §3). What renders below the header is entirely
 * determined by PatientDetailAccess, resolved from the real permission
 * set of whichever role is signed in - not a hardcoded per-role branch.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User $user
 * @var \Aster\Domain\Entity\Patient $patient
 * @var string $tab
 * @var list<array{key:string,label:string}> $tabs
 */

declare(strict_types=1);

use Aster\Presentation\Support\PatientDetailAccess;

$adminPath   = $view->config->adminPath;
$patientPath = $adminPath . '/patients/' . $patient->id;
?>
<div class="grid gap-6">

    <div class="card-pad">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Patient</p>
                <h1 class="mt-1 text-2xl font-extrabold text-medical-900"><?= $view->e($patient->fullName()) ?></h1>
                <p class="mt-1 font-mono text-sm text-medical-700"><?= $view->e($patient->pid->value) ?></p>
            </div>
            <a href="<?= $view->e($adminPath . '/patients') ?>" class="btn-secondary btn-sm">&larr; Back to search</a>
        </div>

        <dl class="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="stat-label">Gender</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->gender->label()) ?></dd></div>
            <div><dt class="stat-label">Date of birth</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->dateOfBirth->format('Y-m-d')) ?> (<?= $patient->age() ?>y)</dd></div>
            <div><dt class="stat-label">Phone</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->phoneNumber->e164) ?></dd></div>
            <div><dt class="stat-label">Blood group</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->bloodGroup?->value ?? 'Unknown') ?></dd></div>
        </dl>

        <?php if (PatientDetailAccess::canSeeClinicalSummary($user) && isset($allergies) && $allergies !== []): ?>
            <div class="mt-6">
                <b class="text-xs font-extrabold uppercase tracking-wider text-rose-600">&#9888; Allergies</b>
                <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($allergies as $allergy): ?>
                        <span class="badge border-rose-200 bg-rose-50 text-rose-700"><?= $view->e($allergy) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-pad !p-0">
        <nav class="flex flex-wrap gap-1 border-b border-slate-100 px-4 pt-2" aria-label="Patient detail sections">
            <?php foreach ($tabs as $entry): ?>
                <a href="<?= $view->e($patientPath . '?tab=' . $entry['key']) ?>"
                   class="rounded-t-lg px-4 py-2.5 text-sm font-bold transition <?= $tab === $entry['key']
                        ? 'border-b-2 border-medical-600 text-medical-700'
                        : 'border-b-2 border-transparent text-slate-500 hover:text-medical-600' ?>"
                   <?= $view->attr($tab === $entry['key'], 'aria-current="page"') ?>>
                    <?= $view->e($entry['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 sm:p-6">
            <?php /* get_defined_vars() carries every tab-specific key the
                     controller passed through (allergies, encounters, notes,
                     orders, ledger...) straight into the tab partial - each
                     tab template documents exactly which of those it expects
                     in its own @var block, same as every other view here. */ ?>
            <?= $view->partial('admin/patients/tabs/' . $tab, get_defined_vars()) ?>
        </div>
    </div>
</div>
