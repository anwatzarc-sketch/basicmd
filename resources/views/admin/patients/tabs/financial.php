<?php
/**
 * Patient Detail - Financial Ledger tab (spec §4.4/§5).
 *
 * Read-only on this page by design: charges and payments are always
 * posted against one specific encounter (BillingController's own
 * ledger/payment routes), so "Record Charge"/"Record Payment" here
 * link out to /admin/billing/ledger pre-scoped to an encounter rather
 * than duplicating that write path - one audited entry point, not two
 * that have to stay in sync.
 *
 * Charges/Payments/Outstanding are computed fresh on every request
 * (SUM() across every encounter this patient has ever had) - never
 * stored, never cached, per spec §5.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\Patient $patient
 * @var \MediCareMini\Domain\ValueObject\Balance $charges
 * @var \MediCareMini\Domain\ValueObject\Balance $paid
 * @var \MediCareMini\Domain\ValueObject\Balance $outstanding
 * @var list<\MediCareMini\Domain\Entity\LedgerEntry> $ledger
 * @var list<\MediCareMini\Domain\Entity\ReceivablePayment> $payments
 */

declare(strict_types=1);

$adminPath = '/' . $view->config->adminPath;
?>
<div class="grid gap-4 sm:grid-cols-3">
    <div class="stat-card">
        <span class="stat-label">Charges</span>
        <b class="mt-2 block text-2xl font-extrabold text-medical-800"><?= $view->e($charges->format()) ?></b>
    </div>
    <div class="stat-card">
        <span class="stat-label">Payments</span>
        <b class="mt-2 block text-2xl font-extrabold text-emerald-700"><?= $view->e($paid->format()) ?></b>
    </div>
    <div class="stat-card">
        <span class="stat-label">Outstanding balance</span>
        <b class="mt-2 block text-2xl font-extrabold <?= $outstanding->isPositive() ? 'text-rose-700' : 'text-emerald-700' ?>"><?= $view->e($outstanding->format()) ?></b>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-2">
    <a href="<?= $view->e($adminPath . '/billing/ledger') ?>" class="btn-primary btn-sm">Record charge</a>
    <a href="<?= $view->e($adminPath . '/billing/ledger') ?>" class="btn-secondary btn-sm">Record payment</a>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div>
        <h2 class="text-base font-extrabold text-medical-900">Recent charges</h2>
        <?php if ($ledger === []): ?>
            <p class="mt-3 text-sm text-slate-500">No charges recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Category</th><th>Description</th><th class="text-right">Total</th><th>Encounter</th></tr></thead>
                    <tbody>
                        <?php foreach ($ledger as $entry): ?>
                            <tr class="<?= $entry->parentEntryId !== null ? 'text-rose-700' : '' ?>">
                                <td><?= $view->e($entry->category->value) ?></td>
                                <td class="text-xs text-slate-500"><?= $view->e($entry->costEntry) ?></td>
                                <td class="text-right font-mono font-bold"><?= $view->e($entry->totalCost->format()) ?></td>
                                <td>
                                    <a href="<?= $view->e($adminPath . '/encounters/' . $entry->encounterId) ?>" class="text-xs text-medical-600 hover:underline">
                                        #<?= $entry->encounterId ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <h2 class="text-base font-extrabold text-medical-900">Recent payments</h2>
        <?php if ($payments === []): ?>
            <p class="mt-3 text-sm text-slate-500">No payments recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Receipt</th><th>Method</th><th class="text-right">Amount</th><th>Encounter</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr class="<?= $payment->parentEntryId !== null ? 'text-rose-700' : '' ?>">
                                <td class="font-mono text-xs"><?= $view->e($payment->receiptId) ?></td>
                                <td><?= $view->e($payment->paymentMethod->value) ?></td>
                                <td class="text-right font-mono font-bold"><?= $view->e($payment->amountPaid->format()) ?></td>
                                <td>
                                    <a href="<?= $view->e($adminPath . '/encounters/' . $payment->encounterId) ?>" class="text-xs text-medical-600 hover:underline">
                                        #<?= $payment->encounterId ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
