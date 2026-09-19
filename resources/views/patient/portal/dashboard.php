<?php
/**
 * Patient portal dashboard (FRS 10.6).
 *
 * Deliberately does not decrypt or display diagnostic RESULTS content -
 * only the order's metadata (category, test, status, date). FRS 10.6
 * requires "diagnostic summaries", not full results, and the FRS's own
 * security section (11.2) requires encrypted clinical content never be
 * exposed without application-level authorisation and decryption
 * handling this screen does not implement - the safe reading of an
 * intentionally ambiguous requirement is to show that a result exists,
 * not what it says.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\Patient $patient
 * @var list<\MediCareMini\Domain\Entity\Encounter> $encounters
 * @var list<\MediCareMini\Domain\Entity\DiagnosticOrder> $diagnostics
 * @var list<array{encounter: \MediCareMini\Domain\Entity\Encounter, balance: float}> $balances
 */

declare(strict_types=1);
?>
<div class="grid gap-6">

    <div class="card-pad">
        <p class="eyebrow">My information</p>
        <h1 class="mt-1 text-2xl font-extrabold text-medical-900"><?= $view->e($patient->fullName()) ?></h1>
        <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="stat-label">Patient ID</dt><dd class="mt-1 font-mono font-semibold text-slate-900"><?= $view->e($patient->pid->value) ?></dd></div>
            <div><dt class="stat-label">Date of birth</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->dateOfBirth->format('Y-m-d')) ?></dd></div>
            <div><dt class="stat-label">Gender</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->gender->value) ?></dd></div>
            <div><dt class="stat-label">Phone</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->phoneNumber->e164) ?></dd></div>
            <?php if ($patient->bloodGroup !== null): ?>
                <div><dt class="stat-label">Blood group</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->bloodGroup->value) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>

    <?php if ($balances !== []): ?>
        <div class="card-pad border-amber-200 bg-amber-50">
            <h2 class="text-base font-extrabold text-amber-900">Outstanding balances</h2>
            <p class="mt-1 text-xs text-amber-700">Please settle these at the billing desk on your next visit.</p>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Visit</th><th>Date</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        <?php foreach ($balances as $row): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= $view->e($row['encounter']->patientVisitNumber->value) ?></td>
                                <td class="text-slate-500"><?= $view->e($row['encounter']->createdAt->format('Y-m-d')) ?></td>
                                <td class="text-right font-mono font-bold text-amber-800">ETB <?= number_format($row['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card-pad">
        <h2 class="text-base font-extrabold text-medical-900">My visits</h2>
        <?php if ($encounters === []): ?>
            <p class="mt-3 text-sm text-slate-500">No visits on record yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Visit</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($encounters as $encounter): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= $view->e($encounter->patientVisitNumber->value) ?></td>
                                <td><?= $view->e($encounter->visitType->label()) ?></td>
                                <td><span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span></td>
                                <td class="text-slate-500"><?= $view->e($encounter->createdAt->format('Y-m-d')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-pad">
        <h2 class="text-base font-extrabold text-medical-900">Diagnostic summaries</h2>
        <?php if ($diagnostics === []): ?>
            <p class="mt-3 text-sm text-slate-500">No diagnostic orders on record yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Category</th><th>Test</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($diagnostics as $order): ?>
                            <tr>
                                <td><?= $view->e($order->category->value) ?></td>
                                <td><?= $view->e($order->testName) ?></td>
                                <td><span class="badge"><?= $view->e($order->status->label()) ?></span></td>
                                <td class="text-slate-500"><?= $view->e($order->createdAt->format('Y-m-d')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
