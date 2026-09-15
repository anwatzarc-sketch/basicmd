<?php
/**
 * Audit trail viewer.
 *
 * Append-only: there is no edit or delete action here by design. The trail is
 * the record of who did what, and a trail that can be altered is not one.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<array<string,mixed>> $entries
 * @var array $filters
 * @var array<string,int|bool> $pagination
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = $view->config->adminPath;

$query = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides), static fn ($v): bool => $v !== null && $v !== '');
    return $params === [] ? '' : '?' . http_build_query($params);
};

/** Colour-codes the action so security events stand out from CMS edits. */
$tone = static function (string $action): string {
    return match (true) {
        str_starts_with($action, 'auth.login_failed'), str_starts_with($action, 'auth.locked')
            => 'border-rose-200 bg-rose-50 text-rose-700',
        str_starts_with($action, 'auth.')    => 'border-violet-200 bg-violet-50 text-violet-700',
        str_starts_with($action, 'payment.') => 'border-amber-200 bg-amber-50 text-amber-800',
        str_starts_with($action, 'user.'), str_starts_with($action, 'settings.')
            => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
};
?>
<form method="get" class="card-pad flex flex-wrap items-end gap-3" data-auto-filter>
    <div class="field">
        <label class="label" for="action">Action</label>
        <input class="input" type="text" id="action" name="action" list="action-list"
               placeholder="e.g. payment.verified" value="<?= $view->e($filters['action'] ?? '') ?>">
        <datalist id="action-list">
            <?php foreach ([
                'auth.login', 'auth.login_failed', 'auth.logout', 'auth.locked_out',
                'appointment.created', 'appointment.status_changed',
                'payment.proof_submitted', 'payment.verified', 'payment.rejected', 'payment.proof_viewed',
                'content.created', 'content.updated', 'content.deleted',
                'user.created', 'user.updated', 'settings.saved',
            ] as $action): ?>
                <option value="<?= $view->e($action) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <button type="submit" class="btn-primary btn-sm">Filter</button>
    <a href="<?= $view->adminUrl('settings/audit') ?>" class="btn-secondary btn-sm">Clear</a>
</form>

<div class="card mt-6">
    <?php if ($entries === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No audit entries match these filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td class="whitespace-nowrap text-xs text-slate-500">
                                <?= $view->e($t->dateTime(new DateTimeImmutable($entry['created_at'] . ' UTC'))) ?>
                            </td>
                            <td class="text-sm font-semibold text-slate-800">
                                <?= $view->e($entry['user_name'] ?? $entry['actor_label'] ?? 'System') ?>
                            </td>
                            <td>
                                <span class="badge <?= $tone((string) $entry['action']) ?>">
                                    <?= $view->e($entry['action']) ?>
                                </span>
                            </td>
                            <td class="max-w-md text-xs text-slate-600">
                                <?= $view->e($entry['summary'] ?? '') ?>
                                <?php if (!empty($entry['target_type'])): ?>
                                    <span class="mt-0.5 block font-mono text-[10px] text-slate-400">
                                        <?= $view->e($entry['target_type']) ?>#<?= (int) $entry['target_id'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="font-mono text-[11px] text-slate-400"><?= $view->e($entry['ip_text'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $view->partial('partials/pagination', [
    'view' => $view, 'pagination' => $pagination,
    'basePath' => $adminPath . '/settings/audit', 'query' => $query,
]) ?>
