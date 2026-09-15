<?php
/**
 * Encounter detail: identity, ledger, payments, and the applicable
 * workflow actions (FRS 10.2/10.3).
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Encounter $encounter
 * @var \Aster\Domain\Entity\Patient|null $patient
 * @var float $balance
 * @var bool $cleared
 * @var list<\Aster\Domain\Entity\LedgerEntry> $ledgerEntries
 * @var list<\Aster\Domain\Entity\ReceivablePayment> $paymentEntries
 * @var list<\Aster\Domain\Entity\WardLocation> $availableBeds
 * @var list<\Aster\Domain\Entity\User> $physicians
 */

declare(strict_types=1);

use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\Enum\VisitType;

$adminPath = $view->config->adminPath;
$encounterPath = $adminPath . '/encounters/' . $encounter->id;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div class="grid gap-6">
        <div class="card-pad">
            <p class="eyebrow">Encounter</p>
            <h2 class="mt-1 font-mono text-2xl font-extrabold text-medical-900"><?= $view->e($encounter->patientVisitNumber->value) ?></h2>
            <p class="mt-1 text-sm text-slate-500">
                <?= $view->e($patient?->fullName() ?? 'Unknown patient') ?>
                <?php if ($patient !== null): ?>
                    &middot; <a href="<?= $view->adminUrl('patients/' . $patient->id) ?>" class="font-bold text-medical-700">View patient record</a>
                <?php endif; ?>
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span>
                <span class="badge"><?= $view->e($encounter->visitType->label()) ?></span>
                <span class="badge <?= $cleared ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' ?>">
                    <?= $cleared ? 'Financially clear' : 'Balance outstanding' ?>
                </span>
            </div>

            <dl class="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="stat-label">Location</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($encounter->locationLabel ?? 'Not assigned') ?></dd></div>
                <div><dt class="stat-label">Physician</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($encounter->physicianName ?? 'Not assigned') ?></dd></div>
                <div><dt class="stat-label">Receivable balance</dt><dd class="mt-1 font-mono font-semibold <?= $balance > 0 ? 'text-rose-700' : 'text-emerald-700' ?>">ETB <?= number_format($balance, 2) ?></dd></div>
            </dl>
        </div>

        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Consumption ledger</h2>
            <?php if ($ledgerEntries === []): ?>
                <p class="mt-3 text-sm text-slate-500">No charges recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th>Category</th><th>Description</th><th>Unit</th><th>Per unit</th><th>Total</th><th>Reason</th></tr></thead>
                        <tbody>
                            <?php foreach ($ledgerEntries as $entry): ?>
                                <tr class="<?= $entry->isReversal() ? 'text-rose-700' : '' ?>">
                                    <td><?= $view->e($entry->category->value) ?></td>
                                    <td><?= $view->e($entry->costEntry) ?></td>
                                    <td class="text-right font-mono"><?= $entry->unit ?></td>
                                    <td class="text-right font-mono"><?= number_format($entry->perUnitCost->toMajor(), 2) ?></td>
                                    <td class="text-right font-mono font-bold"><?= number_format($entry->totalCost->toMajor(), 2) ?></td>
                                    <td class="text-xs text-slate-500"><?= $view->e($entry->reason) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Payments</h2>
            <?php if ($paymentEntries === []): ?>
                <p class="mt-3 text-sm text-slate-500">No payments posted yet.</p>
            <?php else: ?>
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th>Receipt</th><th>Method</th><th>Amount</th><th>Posted by</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($paymentEntries as $payment): ?>
                                <tr class="<?= $payment->isRefund() ? 'text-rose-700' : '' ?>">
                                    <td class="font-mono text-xs"><?= $view->e($payment->receiptId) ?></td>
                                    <td><?= $view->e($payment->paymentMethod->value) ?></td>
                                    <td class="text-right font-mono font-bold"><?= number_format($payment->amountPaid->toMajor(), 2) ?></td>
                                    <td class="text-slate-500"><?= $view->e($payment->accountantName ?? '-') ?></td>
                                    <td class="text-slate-500"><?= $view->e($payment->createdAt->format('Y-m-d H:i')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <a href="<?= $view->adminUrl('billing/ledger?visit=' . urlencode($encounter->patientVisitNumber->value)) ?>" class="btn-secondary btn-sm mt-4">Post a charge or payment</a>
        </div>
    </div>

    <aside class="grid gap-6">
        <?php if ($encounter->status->isAdmissionEligible()): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Admit to inpatient care</h2>
                <?php if ($availableBeds === []): ?>
                    <p class="mt-2 text-xs text-amber-700">No beds are currently available.</p>
                <?php elseif ($physicians === []): ?>
                    <p class="mt-2 text-xs text-amber-700">No active physicians are on record.</p>
                <?php else: ?>
                    <form method="post" action="<?= $view->adminUrl('encounters/upgrade-ipd') ?>" class="mt-4 grid gap-3">
                        <?= $view->csrfField() ?>
                        <input type="hidden" name="patient_visit_number" value="<?= $view->e($encounter->patientVisitNumber->value) ?>">
                        <div class="field">
                            <label class="label" for="target_bed_id">Bed</label>
                            <select class="select" id="target_bed_id" name="target_bed_id" required>
                                <?php foreach ($availableBeds as $bed): ?>
                                    <option value="<?= $bed->id ?>"><?= $view->e($bed->displayLabel()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="physician_id">Physician</label>
                            <select class="select" id="physician_id" name="physician_id" required>
                                <?php foreach ($physicians as $physician): ?>
                                    <option value="<?= $physician->id ?>"><?= $view->e($physician->fullName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary btn-sm w-full">Admit</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$encounter->status->isTerminal()): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Discharge / close</h2>
                <form method="post" action="<?= $view->e($encounterPath . '/discharge') ?>"
                      data-confirm="Discharge this encounter? This cannot be undone."
                      data-confirm-title="Confirm discharge" data-confirm-action="Discharge" data-confirm-variant="primary">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn-primary btn-sm w-full mt-3" <?= $view->attr(!$cleared, 'disabled') ?>>
                        <?= $cleared ? 'Discharge' : 'Balance outstanding' ?>
                    </button>
                </form>

                <?php if (!$cleared): ?>
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-amber-700">Apply an authorised override</summary>
                        <form method="post" action="<?= $view->e($encounterPath . '/override') ?>" class="mt-2 grid gap-2">
                            <?= $view->csrfField() ?>
                            <textarea class="textarea" name="reason" rows="2" required placeholder="Reason for the override"></textarea>
                            <button type="submit" class="btn-secondary btn-sm w-full">Apply override</button>
                        </form>
                    </details>
                <?php endif; ?>

                <form method="post" action="<?= $view->e($encounterPath . '/walk-out') ?>" class="mt-3 grid gap-2">
                    <?= $view->csrfField() ?>
                    <textarea class="textarea" name="reason" rows="2" required placeholder="Walk-out reason"></textarea>
                    <button type="submit" class="btn-ghost btn-sm w-full text-rose-600">Mark as walk-out</button>
                </form>
            </div>
        <?php endif; ?>

        <a href="<?= $view->adminUrl('encounters/workbench') ?>" class="btn-secondary btn-sm">&larr; Back to workbench</a>
    </aside>
</div>
