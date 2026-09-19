<?php
/**
 * Patient Detail - Diagnostic Orders tab (spec §4.1/§4.5).
 *
 * $orders is already scoped to Lab-only at the query itself for a Lab
 * Technician (PatientDetailAccess::diagnosticCategoryScope() ->
 * DiagnosticOrderRepositoryInterface::forPatient()'s $category param) -
 * this view never filters a broader list down in PHP, so there is no
 * path where an Imaging/PACS row could leak into a Lab Technician's
 * view by a display-layer bug.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\User $user
 * @var \MediCareMini\Domain\Entity\Patient $patient
 * @var list<\MediCareMini\Domain\Entity\DiagnosticOrder> $orders
 * @var array<int, string> $orderResults id => decrypted plaintext (Imaging/PACS results, which are free text)
 * @var array<int, \MediCareMini\Domain\DTO\LabReport|null> $labReports id => decoded structured result (Lab orders only)
 * @var list<\MediCareMini\Domain\Entity\Encounter> $openEncounters
 * @var list<\MediCareMini\Domain\Enum\DiagnosticCategory> $categories
 */

declare(strict_types=1);

use MediCareMini\Domain\Enum\DiagnosticStatus;
use MediCareMini\Presentation\Support\PatientDetailAccess;

$adminPath = '/' . $view->config->adminPath;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div>
        <h2 class="text-base font-extrabold text-medical-900">Diagnostic orders</h2>

        <?php if ($orders === []): ?>
            <p class="mt-3 text-sm text-slate-500">No diagnostic orders recorded yet.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-3">
                <?php foreach ($orders as $order): ?>
                    <li class="rounded-xl border border-slate-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <b class="text-sm text-slate-900"><?= $view->e($order->testName) ?></b>
                                <span class="ml-2 badge"><?= $view->e($order->category->value) ?></span>
                            </div>
                            <span class="badge"><?= $view->e($order->status->label()) ?></span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            <?php if ($order->accessionNumber !== null): ?>
                                <span class="font-mono"><?= $view->e($order->accessionNumber->value) ?></span> &middot;
                            <?php endif; ?>
                            <?= $view->e($order->testCode) ?> &middot; ICD <?= $view->e($order->icdCode) ?>
                            &middot; ordered by <?= $view->e($order->physicianName ?? 'Unknown') ?>
                            &middot; <?= $view->e($order->createdAt->format('Y-m-d H:i')) ?>
                        </p>

                        <?php if ($order->isLab()): ?>
                            <?php
                            /*
                             * A Lab order is worked in the Laboratory module, not
                             * inline here: that is where the structured grid, the
                             * reference intervals and the printable sheet live.
                             * This panel stays the patient-centred READ of every
                             * diagnostic order, and hands off rather than growing
                             * a second, weaker result editor beside the real one.
                             */
                            $labReport = $labReports[$order->id] ?? null;
                            ?>
                            <?php if ($labReport !== null && $labReport->isStructured()): ?>
                                <p class="mt-3 text-sm text-slate-700">
                                    <?= (int) count($labReport->reportedLines()) ?> parameter<?= count($labReport->reportedLines()) === 1 ? '' : 's' ?> reported<?php
                                    if ($labReport->abnormalCount() > 0): ?>,
                                        <b class="text-rose-700"><?= (int) $labReport->abnormalCount() ?> outside reference</b><?php
                                    endif; ?>.
                                </p>
                                <?php if ($labReport->impression !== ''): ?>
                                    <p class="mt-2 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><?= $view->e($labReport->impression) ?></p>
                                <?php endif; ?>
                            <?php elseif (isset($orderResults[$order->id])): ?>
                                <p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><?= $view->e($orderResults[$order->id]) ?></p>
                            <?php endif; ?>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <?php if (!$order->status->isTerminal() && PatientDetailAccess::canRecordResult($user, $order->category)): ?>
                                    <a href="<?= $view->e($adminPath . '/lab/orders/' . $order->id . '/results') ?>" class="btn-secondary btn-sm">
                                        <?= $order->hasResults() ? 'Edit results' : 'Enter results' ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($order->hasResults()): ?>
                                    <a href="<?= $view->e($adminPath . '/lab/orders/' . $order->id . '/report') ?>" class="btn-ghost btn-sm">Report sheet</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>

                        <?php if (isset($orderResults[$order->id])): ?>
                            <p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><?= $view->e($orderResults[$order->id]) ?></p>
                        <?php endif; ?>

                        <?php if (!$order->status->isTerminal() && PatientDetailAccess::canRecordResult($user, $order->category)): ?>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-bold text-medical-600">Update status / record result</summary>
                                <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/diagnostics/' . $order->id . '/result') ?>" class="mt-3 grid gap-2">
                                    <?= $view->csrfField() ?>
                                    <select class="select" name="status" required>
                                        <?php foreach (DiagnosticStatus::all() as $status): ?>
                                            <?php if ($order->status->canTransitionTo($status)): ?>
                                                <option value="<?= $view->e($status->value) ?>"><?= $view->e($status->label()) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea class="textarea" name="results" rows="3" placeholder="Result (saved only when moving to Completed)"></textarea>
                                    <button type="submit" class="btn-secondary btn-sm">Update order</button>
                                </form>
                            </details>
                        <?php endif; ?>

                        <?php endif; /* Lab vs Imaging/PACS */ ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (PatientDetailAccess::canOrderDiagnostics($user)): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Place an order</h2>
                <?php if ($openEncounters === []): ?>
                    <p class="mt-2 text-xs text-amber-700">This patient has no open encounter to order against.</p>
                <?php else: ?>
                    <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/diagnostics') ?>" class="mt-4 grid gap-3">
                        <?= $view->csrfField() ?>
                        <div class="field">
                            <label class="label" for="dx_encounter_id">Encounter</label>
                            <select class="select" id="dx_encounter_id" name="encounter_id" required>
                                <?php foreach ($openEncounters as $encounter): ?>
                                    <option value="<?= $encounter->id ?>"><?= $view->e($encounter->patientVisitNumber->value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="dx_category">Category</label>
                            <select class="select" id="dx_category" name="category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $view->e($category->value) ?>"><?= $view->e($category->value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="test_code">Test code</label>
                            <input class="input" type="text" id="test_code" name="test_code" required>
                        </div>
                        <div class="field">
                            <label class="label" for="test_name">Test name</label>
                            <input class="input" type="text" id="test_name" name="test_name" required>
                        </div>
                        <div class="field">
                            <label class="label" for="dx_icd_code">ICD code</label>
                            <input class="input" type="text" id="dx_icd_code" name="icd_code" required>
                        </div>
                        <button type="submit" class="btn-primary btn-sm w-full">Place order</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
