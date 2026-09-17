<?php
/**
 * Finance verification queue.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\Payment> $payments
 * @var array $filters
 * @var list<\Aster\Domain\Enum\ProofStatus> $statuses
 * @var list<\Aster\Domain\Entity\PaymentMethod> $methods
 * @var int $pendingCount
 * @var array<string,int|bool> $pagination
 * @var bool $canVerify
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = '/' . $view->config->adminPath;

$query = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides), static fn ($v): bool => $v !== null && $v !== '');
    return $params === [] ? '' : '?' . http_build_query($params);
};
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <?php if ($pendingCount > 0): ?>
        <div class="alert-warning flex-1" role="status">
            <span><b><?= (int) $pendingCount ?></b> receipt<?= $pendingCount === 1 ? '' : 's' ?> awaiting verification.</span>
        </div>
    <?php endif; ?>
    <a href="<?= $view->adminUrl('payments/methods') ?>" class="btn-secondary btn-sm">Payment methods</a>
</div>

<form method="get" class="card-pad mt-6 grid gap-4 sm:grid-cols-3" data-auto-filter>
    <div class="field sm:col-span-1">
        <label class="label" for="q">Search</label>
        <input class="input" type="search" id="q" name="q"
               placeholder="Reference, patient or transaction"
               value="<?= $view->e($filters['search'] ?? '') ?>">
    </div>
    <div class="field">
        <label class="label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= $view->e($status->value) ?>"
                    <?= $view->attr(($filters['status'] ?? '') === $status->value, 'selected') ?>>
                    <?= $view->e($status->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label class="label" for="method_id">Method</label>
        <select class="select" id="method_id" name="method_id">
            <option value="">All methods</option>
            <?php foreach ($methods as $method): ?>
                <option value="<?= $method->id ?>" <?= $view->attr((int) ($filters['method_id'] ?? 0) === $method->id, 'selected') ?>>
                    <?= $view->e($method->provider) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card mt-6">
    <?php if ($payments === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">Nothing in this queue.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Patient</th>
                        <th>Method</th>
                        <th>Transaction</th>
                        <th class="text-right">Amount</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="font-mono text-xs font-bold text-medical-700"><?= $view->e($payment->bookingRef ?? '') ?></td>
                            <td class="font-semibold text-slate-900"><?= $view->e($payment->patientName ?? '') ?></td>
                            <td class="text-slate-600"><?= $view->e($payment->methodLabel ?? '—') ?></td>
                            <td class="font-mono text-xs text-slate-500"><?= $view->e($payment->transferRef ?? '—') ?></td>
                            <td class="text-right font-bold text-slate-900"><?= $view->e($t->money($payment->amount)) ?></td>
                            <td class="text-xs text-slate-500"><?= $view->e($t->relative($payment->createdAt)) ?></td>
                            <td><span class="badge <?= $view->e($payment->status->badgeClass()) ?>"><?= $view->e($payment->status->label()) ?></span></td>
                            <td class="text-right">
                                <a href="<?= $view->adminUrl('payments/' . $payment->id) ?>" class="btn-ghost btn-sm">
                                    <?= $canVerify && $payment->isPendingReview() ? 'Review' : 'View' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $view->partial('partials/pagination', [
    'view' => $view, 'pagination' => $pagination,
    'basePath' => $adminPath . '/payments', 'query' => $query,
]) ?>
