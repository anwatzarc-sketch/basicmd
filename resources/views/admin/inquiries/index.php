<?php
/**
 * Patient enquiry inbox.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\ContactInquiry> $inquiries
 * @var list<\Aster\Domain\Enum\InquiryStatus> $statuses
 * @var array $filters
 * @var int $unread
 * @var array<string,int|bool> $pagination
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = $view->config->adminPath;

$query = static function (array $overrides) use ($filters): string {
    $params = array_filter(array_merge($filters, $overrides), static fn ($v): bool => $v !== null && $v !== '');
    return $params === [] ? '' : '?' . http_build_query($params);
};
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3" data-auto-filter>
        <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" type="search" id="q" name="q" placeholder="Name, phone, email or message"
                   value="<?= $view->e($filters['search'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $view->e($status->value) ?>" <?= $view->attr(($filters['status'] ?? '') === $status->value, 'selected') ?>>
                        <?= $view->e($status->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <?php if ($unread > 0): ?>
        <span class="badge border-amber-200 bg-amber-50 text-amber-800"><?= (int) $unread ?> needing attention</span>
    <?php endif; ?>
</div>

<div class="card mt-6">
    <?php if ($inquiries === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No enquiries match these filters.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($inquiries as $inquiry): ?>
                <li>
                    <a href="<?= $view->adminUrl('inquiries/' . $inquiry->id) ?>"
                       class="flex flex-wrap items-start justify-between gap-4 p-5 transition hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <b class="text-slate-900"><?= $view->e($inquiry->name) ?></b>
                                <span class="text-xs text-slate-500"><?= $view->e($inquiry->phone->formatNational()) ?></span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600"><?= $view->e($inquiry->subjectLine()) ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?= $view->e($inquiry->preview(110)) ?></p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="badge <?= $view->e($inquiry->status->badgeClass()) ?>">
                                <?= $view->e($inquiry->status->label()) ?>
                            </span>
                            <span class="text-[11px] text-slate-400"><?= $view->e($t->relative($inquiry->createdAt)) ?></span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?= $view->partial('partials/pagination', [
    'view' => $view, 'pagination' => $pagination,
    'basePath' => $adminPath . '/inquiries', 'query' => $query,
]) ?>
