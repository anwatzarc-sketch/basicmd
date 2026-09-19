<?php
/**
 * Laboratory work queue.
 *
 * $orders is already Lab-only and already ward-scoped when it reaches
 * here (LabController::queue()) - this view never narrows a wider list,
 * so there is no display-layer path by which an Imaging order or an
 * out-of-ward patient could appear.
 *
 * The status chips read in laboratory language (labLabel()) rather than
 * in the record's own vocabulary: a technician is waiting on a specimen,
 * not on an "ORDERED" row.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\DiagnosticOrder> $orders
 * @var list<\Aster\Domain\Enum\DiagnosticStatus> $statuses
 * @var \Aster\Domain\Enum\DiagnosticStatus|null $activeStatus
 * @var string $searchTerm
 * @var array<string, int> $counts
 * @var bool $canOrder
 * @var bool $canResult
 */

declare(strict_types=1);

use Aster\Domain\Enum\DiagnosticStatus;

$total = array_sum($counts);
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h2 class="text-base font-extrabold text-medical-900">Laboratory orders &amp; work queue</h2>
        <p class="mt-1 text-sm text-slate-500">
            Raise requisitions, record results against reference intervals, and release signed report sheets.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= $view->adminUrl('lab/catalog') ?>" class="btn-secondary btn-sm">Test directory</a>
        <?php if ($canOrder): ?>
            <a href="<?= $view->adminUrl('lab/requisitions/create') ?>" class="btn-primary btn-sm">New requisition</a>
        <?php endif; ?>
    </div>
</div>

<div class="card-pad mt-6 grid gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="field min-w-[18rem] flex-1">
            <label class="label" for="q">Search</label>
            <input class="input" type="search" id="q" name="q" value="<?= $view->e($searchTerm) ?>"
                   placeholder="Accession, barcode, patient, PID, visit number or test">
        </div>
        <?php if ($activeStatus !== null): ?>
            <input type="hidden" name="status" value="<?= $view->e($activeStatus->value) ?>">
        <?php endif; ?>
        <button type="submit" class="btn-secondary btn-sm">Search</button>
        <?php if ($searchTerm !== '' || $activeStatus !== null): ?>
            <a href="<?= $view->adminUrl('lab') ?>" class="btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= $view->adminUrl('lab' . ($searchTerm !== '' ? '?q=' . urlencode($searchTerm) : '')) ?>"
           class="chip <?= $activeStatus === null ? 'chip-active' : '' ?>">
            All orders
            <span class="font-mono text-[0.7rem]"><?= (int) $total ?></span>
        </a>
        <?php foreach ($statuses as $status): ?>
            <?php
            $query = ['status' => $status->value] + ($searchTerm !== '' ? ['q' => $searchTerm] : []);
            ?>
            <a href="<?= $view->adminUrl('lab?' . http_build_query($query)) ?>"
               class="chip <?= $activeStatus === $status ? 'chip-active' : '' ?>">
                <?= $view->e($status->labLabel()) ?>
                <span class="font-mono text-[0.7rem]"><?= (int) ($counts[$status->value] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mt-6">
    <?php if ($orders === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">
            <?= $searchTerm !== '' || $activeStatus !== null
                ? 'No laboratory orders match that filter.'
                : 'No laboratory orders yet.' ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table min-w-[60rem]">
                <thead>
                    <tr>
                        <th>Accession / specimen</th>
                        <th>Patient</th>
                        <th>Panel</th>
                        <th>Ordered by</th>
                        <th>Status</th>
                        <th>Raised</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <span class="block font-mono text-xs font-bold text-slate-900">
                                    <?= $view->e($order->accessionNumber?->value ?? ('#' . $order->id)) ?>
                                </span>
                                <span class="block text-[11px] text-slate-400">
                                    <?= $view->e($order->specimenType ?? 'Specimen not specified') ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($order->patientId !== null): ?>
                                    <a class="font-semibold text-medical-700 hover:underline"
                                       href="<?= $view->adminUrl('patients/' . $order->patientId . '?tab=diagnostics') ?>">
                                        <?= $view->e($order->patientName ?? 'Unknown') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-semibold text-slate-900"><?= $view->e($order->patientName ?? 'Unknown') ?></span>
                                <?php endif; ?>
                                <span class="block font-mono text-[11px] text-slate-400">
                                    <?= $view->e($order->patientPid ?? '-') ?> &middot; <?= $view->e($order->patientAgeGender()) ?>
                                </span>
                            </td>

                            <td>
                                <span class="block text-slate-700"><?= $view->e($order->testName) ?></span>
                                <span class="block font-mono text-[11px] text-slate-400">
                                    <?= $view->e($order->visitNumber ?? '-') ?>
                                </span>
                            </td>

                            <td class="text-slate-500"><?= $view->e($order->physicianName ?? '-') ?></td>

                            <td>
                                <span class="<?= $view->e($order->status->chipClass()) ?>">
                                    <?= $view->e($order->status->labLabel()) ?>
                                </span>
                            </td>

                            <td class="font-mono text-[11px] text-slate-400">
                                <?= $view->e($order->createdAt->format('Y-m-d H:i')) ?>
                            </td>

                            <td class="space-x-1 text-right">
                                <?php if ($canResult && $order->status !== DiagnosticStatus::CANCELLED): ?>
                                    <a href="<?= $view->adminUrl('lab/orders/' . $order->id . '/results') ?>" class="btn-secondary btn-sm">
                                        <?= $order->hasResults() ? 'Edit results' : 'Enter results' ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($order->hasResults()): ?>
                                    <a href="<?= $view->adminUrl('lab/orders/' . $order->id . '/report') ?>" class="btn-ghost btn-sm">Report</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
