<?php
/**
 * Health Knowledge Hub listing.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Enum\Locale $locale
 * @var list<\MediCareMini\Domain\Entity\Article> $articles
 * @var list<\MediCareMini\Domain\Entity\Article> $mostRead
 * @var array<string,int> $categories
 * @var array{category:?string, q:?string} $filters
 * @var array<string,int|bool> $pagination
 */

declare(strict_types=1);

$query = static function (array $overrides) use ($filters): string {
    $params = array_filter([
        'category' => $overrides['category'] ?? $filters['category'] ?? null,
        'q'        => $overrides['q'] ?? $filters['q'] ?? null,
        'page'     => $overrides['page'] ?? null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    return $params === [] ? '' : '?' . http_build_query($params);
};
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <span class="eyebrow"><?= $view->t('articles.eyebrow') ?></span>
    <h1 class="section-title"><?= $view->t('articles.title') ?></h1>

    <div class="mt-10 grid gap-10 lg:grid-cols-[1fr_280px]">
        <div>
            <form method="get" class="flex flex-wrap items-center gap-3" role="search">
                <label class="sr-only" for="q"><?= $view->t('articles.search') ?></label>
                <input class="input max-w-xs" type="search" id="q" name="q"
                       placeholder="<?= $view->e($view->tRaw('articles.search')) ?>"
                       value="<?= $view->e($filters['q'] ?? '') ?>">
                <?php if (($filters['category'] ?? null) !== null): ?>
                    <input type="hidden" name="category" value="<?= $view->e($filters['category']) ?>">
                <?php endif; ?>
                <button class="btn-secondary btn-sm" type="submit"><?= $view->t('form.search') ?></button>
            </form>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="<?= $view->url('health') ?>" class="chip <?= ($filters['category'] ?? null) === null ? 'chip-active' : '' ?>">
                    <?= $view->t('articles.all') ?>
                </a>
                <?php foreach ($categories as $category => $count): ?>
                    <a href="<?= $view->url('health' . $query(['category' => $category, 'page' => null])) ?>"
                       class="chip <?= ($filters['category'] ?? null) === $category ? 'chip-active' : '' ?>">
                        <?= $view->e($category) ?> <span class="opacity-60"><?= (int) $count ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($articles === []): ?>
                <p class="empty-state mt-12">
                    <?= $view->t('articles.none') ?>
                </p>
            <?php else: ?>
                <div class="mt-8 grid gap-5 sm:grid-cols-2">
                    <?php foreach ($articles as $article): ?>
                        <article class="article-card">
                            <div>
                                <?php $cover = $view->media($article->coverImage); ?>
                                <?php if ($cover !== null): ?>
                                    <img src="<?= $cover ?>" alt="<?= $view->e($article->coverAlt ?? '') ?>"
                                         class="mb-4 h-40 w-full rounded-2xl object-cover" loading="lazy"
                                         width="400" height="160">
                                <?php endif; ?>

                                <span class="text-xs font-extrabold uppercase tracking-wider text-medical-600 dark:text-medical-300">
                                    <?= $view->e($article->category) ?>
                                </span>
                                <h2 class="mt-2 text-lg font-extrabold leading-snug text-medical-900 dark:text-medical-100">
                                    <a href="<?= $view->url('health/' . $article->slug) ?>" class="hover:text-medical-600 dark:hover:text-medical-300">
                                        <?= $view->e($article->heading($locale)) ?>
                                    </a>
                                </h2>
                                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                    <?= $view->excerpt($article->summary($locale), 130) ?>
                                </p>
                            </div>

                            <div class="mt-6 flex items-center justify-between text-xs">
                                <span class="text-slate-500 dark:text-slate-400">
                                    <?= $view->t('articles.read_time', ['minutes' => $article->estimatedReadMinutes($locale)]) ?>
                                </span>
                                <a href="<?= $view->url('health/' . $article->slug) ?>"
                                   class="font-bold text-medical-700 hover:underline dark:text-medical-300">
                                    <?= $view->t('articles.read') ?> &rarr;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?= $view->partial('partials/pagination', [
                    'view'       => $view,
                    'pagination' => $pagination,
                    'basePath'   => 'health',
                    'query'      => $query,
                ]) ?>
            <?php endif; ?>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <?php if ($mostRead !== []): ?>
                <div class="card-pad">
                    <h2 class="panel-title">
                        <?= $view->t('common.view_all') ?>
                    </h2>
                    <ul class="mt-4 grid gap-4">
                        <?php foreach ($mostRead as $item): ?>
                            <li>
                                <a href="<?= $view->url('health/' . $item->slug) ?>"
                                   class="text-sm font-semibold leading-snug text-medical-900 hover:text-medical-600 dark:text-medical-100 dark:hover:text-medical-300">
                                    <?= $view->e($item->heading($locale)) ?>
                                </a>
                                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400"><?= $view->e($item->category) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="cta-panel-dark mt-5">
                <h2 class="text-base font-bold"><?= $view->t('booking.title') ?></h2>
                <p class="mt-2 text-sm leading-relaxed text-white/75"><?= $view->t('booking.lead') ?></p>
                <a href="<?= $view->url('book') ?>" class="btn-invert btn-sm mt-4 w-full">
                    <?= $view->t('nav.book') ?>
                </a>
            </div>
        </aside>
    </div>
</section>
