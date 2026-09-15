<?php
/**
 * Article list with inline status transitions.
 *
 * Only transitions the state machine permits are offered, and publishing is
 * hidden entirely from users without articles.publish.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\Article> $articles
 * @var list<\Aster\Domain\Enum\ArticleStatus> $statuses
 * @var array $filters
 * @var array<string,int|bool> $pagination
 * @var bool $canPublish
 */

declare(strict_types=1);

use Aster\Domain\Enum\ArticleStatus;

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
            <input class="input" type="search" id="q" name="q" value="<?= $view->e($filters['q'] ?? '') ?>">
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
    <a href="<?= $view->adminUrl('articles/create') ?>" class="btn-primary btn-sm">Write article</a>
</div>

<div class="card mt-6">
    <?php if ($articles === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No articles yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Title</th><th>Category</th><th>Author</th><th>Views</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td>
                                <b class="block text-slate-900"><?= $view->e($article->title) ?></b>
                                <span class="text-xs text-slate-500">
                                    <?php if ($article->hasTranslation()): ?>
                                        <span class="text-emerald-600">EN + AM</span>
                                    <?php else: ?>
                                        <span class="text-amber-600">English only</span>
                                    <?php endif; ?>
                                    &middot; <?= $view->e($article->schemaType->value) ?>
                                </span>
                            </td>
                            <td class="text-slate-600"><?= $view->e($article->category) ?></td>
                            <td class="text-xs text-slate-500"><?= $view->e($article->authorName ?? '—') ?></td>
                            <td class="text-slate-600"><?= (int) $article->views ?></td>
                            <td><span class="badge <?= $view->e($article->status->badgeClass()) ?>"><?= $view->e($article->status->label()) ?></span></td>
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <?php if ($article->isPublic()): ?>
                                        <a href="<?= $view->url('health/' . $article->slug) ?>" target="_blank" rel="noopener"
                                           class="btn-ghost btn-sm">View</a>
                                    <?php endif; ?>
                                    <a href="<?= $view->adminUrl('articles/' . $article->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>

                                    <?php foreach ($article->status->allowedTransitions() as $target): ?>
                                        <?php if ($target->requiresPublishPermission() && !$canPublish) { continue; } ?>
                                        <?php if ($target === ArticleStatus::ARCHIVED) { continue; } ?>
                                        <form method="post" action="<?= $view->adminUrl('articles/' . $article->id . '/status') ?>">
                                            <?= $view->csrfField() ?>
                                            <input type="hidden" name="status" value="<?= $view->e($target->value) ?>">
                                            <button type="submit" class="btn-ghost btn-sm text-medical-700">
                                                <?= $view->e($target === ArticleStatus::PUBLISHED ? 'Publish' : $target->label()) ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>

                                    <form method="post" action="<?= $view->adminUrl('articles/' . $article->id . '/delete') ?>"
                                          data-confirm="Delete this article?">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn-ghost btn-sm text-rose-600">Delete</button>
                                    </form>
                                </div>
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
    'basePath' => $adminPath . '/articles', 'query' => $query,
]) ?>
