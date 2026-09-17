<?php
/**
 * Manual consumption logging and payment posting (FRS 10.4/10.5).
 *
 * An accountant looks an encounter up by its visit number - not by patient
 * name, and not by appointment - because the ledger and the discharge gate
 * are both scoped to one encounter, and a patient with a prior visit could
 * otherwise have charges posted against the wrong one.
 *
 * @var \Aster\Presentation\View\View $view
 * @var string $searchTerm
 * @var bool $notFound
 * @var \Aster\Domain\Entity\Encounter|null $encounter
 * @var list<\Aster\Domain\Entity\LedgerEntry> $entries
 * @var list<\Aster\Domain\Entity\ReceivablePayment> $payments
 * @var float|null $balance
 * @var list<\Aster\Domain\Enum\LedgerCategory> $categories
 * @var list<\Aster\Domain\Enum\LedgerMode> $modes
 * @var list<\Aster\Domain\Enum\ReceivablePaymentMethod> $paymentMethods
 */

declare(strict_types=1);

use Aster\Domain\Enum\VisitType;

$adminPath = '/' . $view->config->adminPath;

// The ledger's mode column is OPD/IPD only (FRS 7.1 has no ER value) - an
// ER encounter's charges are logged under whichever the patient's stay
// resolves to, so this is only a sensible default, not a derived fact.
$defaultLedgerMode = $encounter !== null && $encounter->visitType === VisitType::IPD ? 'IPD' : 'OPD';
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="field">
            <label class="label" for="visit">Encounter visit number</label>
            <input class="input font-mono" type="text" id="visit" name="visit" value="<?= $view->e($searchTerm) ?>"
                   placeholder="VIS-20260916-00001" autofocus>
        </div>
        <button type="submit" class="btn-primary btn-sm">Find encounter</button>
    </form>
    <a href="<?= $view->adminUrl('encounters/workbench') ?>" class="btn-secondary btn-sm">&larr; Back to workbench</a>
</div>

<?php if ($notFound): ?>
    <div class="card mt-6">
        <p class="p-10 text-center text-sm text-slate-500">No encounter matches that visit number.</p>
    </div>
<?php endif; ?>

<?php if ($encounter !== null): ?>
    <div class="grid gap-6 mt-6 xl:grid-cols-[1.4fr_0.6fr]">

        <div class="grid gap-6">
            <div class="card-pad">
                <p class="eyebrow">Encounter</p>
                <h2 class="mt-1 font-mono text-2xl font-extrabold text-medical-900"><?= $view->e($encounter->patientVisitNumber->value) ?></h2>
                <p class="mt-1 text-sm text-slate-500">
                    <a href="<?= $view->adminUrl('encounters/' . $encounter->id) ?>" class="font-bold text-medical-700">View full encounter</a>
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span>
                    <span class="badge"><?= $view->e($encounter->visitType->label()) ?></span>
                </div>
                <dl class="mt-6">
                    <dt class="stat-label">Receivable balance</dt>
                    <dd class="mt-1 font-mono text-xl font-bold <?= $balance > 0 ? 'text-rose-700' : 'text-emerald-700' ?>">
                        ETB <?= number_format($balance, 2) ?>
                    </dd>
                </dl>
            </div>

            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Consumption ledger</h2>
                <?php if ($entries === []): ?>
                    <p class="mt-3 text-sm text-slate-500">No charges recorded yet.</p>
                <?php else: ?>
                    <div class="table-wrap mt-4">
                        <table class="table">
                            <thead><tr><th>Category</th><th>Description</th><th>Unit</th><th>Per unit</th><th>Total</th><th>Reason</th></tr></thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
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
                <?php if ($payments === []): ?>
                    <p class="mt-3 text-sm text-slate-500">No payments posted yet.</p>
                <?php else: ?>
                    <div class="table-wrap mt-4">
                        <table class="table">
                            <thead><tr><th>Receipt</th><th>Method</th><th>Amount</th><th>Posted by</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
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
            </div>
        </div>

        <aside class="grid gap-6">
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Post a charge</h2>
                <form method="post" action="<?= $view->adminUrl('billing/ledger') ?>" class="mt-4 grid gap-3">
                    <?= $view->csrfField() ?>
                    <input type="hidden" name="patient_visit_number" value="<?= $view->e($encounter->patientVisitNumber->value) ?>">

                    <div class="field">
                        <label class="label" for="category">Category</label>
                        <select class="select" id="category" name="category" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $view->e($category->value) ?>"><?= $view->e($category->value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="cost_entry">Description</label>
                        <input class="input" type="text" id="cost_entry" name="cost_entry" required placeholder="e.g. Paracetamol 500mg">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="field">
                            <label class="label" for="unit">Unit</label>
                            <input class="input" type="number" id="unit" name="unit" min="1" step="1" value="1" required>
                        </div>
                        <div class="field">
                            <label class="label" for="per_unit_cost">Per unit (ETB)</label>
                            <input class="input" type="number" id="per_unit_cost" name="per_unit_cost" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="mode">Mode</label>
                        <select class="select" id="mode" name="mode" required>
                            <?php foreach ($modes as $mode): ?>
                                <option value="<?= $view->e($mode->value) ?>" <?= $view->attr($mode->value === $defaultLedgerMode, 'selected') ?>><?= $view->e($mode->value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="reason">Reason</label>
                        <textarea class="textarea" id="reason" name="reason" rows="2" required></textarea>
                    </div>

                    <button type="submit" class="btn-primary btn-sm w-full">Record charge</button>
                </form>
            </div>

            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Post a payment</h2>
                <form method="post" action="<?= $view->adminUrl('billing/payments') ?>" class="mt-4 grid gap-3">
                    <?= $view->csrfField() ?>
                    <input type="hidden" name="patient_visit_number" value="<?= $view->e($encounter->patientVisitNumber->value) ?>">

                    <div class="field">
                        <label class="label" for="payment_method">Method</label>
                        <select class="select" id="payment_method" name="payment_method" required>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= $view->e($method->value) ?>"><?= $view->e($method->value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="amount_paid">Amount (ETB)</label>
                        <input class="input" type="number" id="amount_paid" name="amount_paid" min="0.01" step="0.01" required>
                    </div>

                    <div class="field">
                        <label class="label" for="reference_note">Reference note</label>
                        <input class="input" type="text" id="reference_note" name="reference_note" placeholder="Optional">
                    </div>

                    <button type="submit" class="btn-primary btn-sm w-full">Record payment</button>
                </form>
            </div>
        </aside>
    </div>
<?php endif; ?>
