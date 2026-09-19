<?php
/**
 * Article reader.
 *
 * The body is emitted through View::raw(), which routes it through
 * HtmlSanitiser. CMS content is stored already-sanitised, so this is the
 * second of two passes - belt and braces on the one place where stored HTML
 * reaches a page.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Enum\Locale $locale
 * @var \MediCareMini\Domain\Entity\Article $article
 * @var list<\MediCareMini\Domain\Entity\Article> $related
 */

declare(strict_types=1);

$t    = $view->translator;
$body = $article->body($locale);

/** Amharic readers get a slightly narrower measure and looser leading. */
$proseClass = $view->isAmharic() ? 'prose-article prose-ethiopic' : 'prose-article';
?>
<article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

    <nav class="text-sm font-semibold text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('') ?>"><?= $view->t('nav.home') ?></a></li>
            <li aria-hidden="true">/</li>
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('health') ?>"><?= $view->t('nav.articles') ?></a></li>
            <li aria-hidden="true">/</li>
            <li class="text-slate-500 dark:text-slate-400"><?= $view->e($article->category) ?></li>
        </ol>
    </nav>

    <header class="mt-6">
        <span class="text-xs font-extrabold uppercase tracking-wider text-medical-600 dark:text-medical-300">
            <?= $view->e($article->category) ?>
        </span>
        <h1 class="page-title mt-3 leading-tight">
            <?= $view->e($article->heading($locale)) ?>
        </h1>

        <?php if ($article->summary($locale) !== null): ?>
            <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                <?= $view->e($article->summary($locale)) ?>
            </p>
        <?php endif; ?>

        <!-- Byline: authorship and clinical review are what make health
             content trustworthy to both readers and search engines. -->
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-y border-slate-200 py-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            <?php if ($article->authorName !== null): ?>
                <span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200"><?= $view->t('articles.written_by') ?>:</span>
                    <?= $view->e($article->authorName) ?>
                    <?php if ($article->authorSpecialty !== null): ?>
                        <span class="text-slate-500 dark:text-slate-400">(<?= $view->e($article->authorSpecialty) ?>)</span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>

            <?php if ($article->reviewerName !== null): ?>
                <span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200"><?= $view->t('articles.reviewed_by') ?>:</span>
                    <?= $view->e($article->reviewerName) ?>
                </span>
            <?php endif; ?>

            <?php if ($article->publishedAt !== null): ?>
                <time datetime="<?= $view->e($article->publishedAt->format('Y-m-d')) ?>">
                    <?= $view->t('articles.published', ['date' => $t->date($article->publishedAt)]) ?>
                </time>
            <?php endif; ?>

            <span><?= $view->t('articles.read_time', ['minutes' => $article->estimatedReadMinutes($locale)]) ?></span>
        </div>
    </header>

    <?php $cover = $view->media($article->coverImage); ?>
    <?php if ($cover !== null): ?>
        <img src="<?= $cover ?>" alt="<?= $view->e($article->coverAlt ?? '') ?>"
             class="mt-8 w-full rounded-3xl object-cover" width="768" height="384" loading="eager">
    <?php endif; ?>

    <div class="<?= $proseClass ?> mt-8">
        <?= $view->raw($body) ?>
    </div>

    <!-- Medical disclaimer: required on health content, both ethically and
         for the site's own protection. -->
    <aside class="alert-info mt-10">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-normal leading-relaxed"><?= $view->t('articles.disclaimer') ?></span>
    </aside>

    <div class="mt-10 rounded-3xl bg-medical-50 p-6 text-center dark:bg-medical-950/40 sm:p-8">
        <h2 class="subsection-title"><?= $view->t('booking.title') ?></h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-600 dark:text-slate-300"><?= $view->t('booking.lead') ?></p>
        <a href="<?= $view->url('book') ?>" class="btn-primary mt-5"><?= $view->t('nav.book') ?></a>
    </div>

    <?php if ($related !== []): ?>
        <section class="mt-14">
            <h2 class="subsection-title"><?= $view->t('articles.related') ?></h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <?php foreach ($related as $item): ?>
                    <a href="<?= $view->url('health/' . $item->slug) ?>"
                       class="article-card">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-medical-600 dark:text-medical-300">
                            <?= $view->e($item->category) ?>
                        </span>
                        <h3 class="mt-2 text-sm font-bold leading-snug text-medical-900 dark:text-medical-100">
                            <?= $view->e($item->heading($locale)) ?>
                        </h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <p class="mt-10">
        <a href="<?= $view->url('health') ?>" class="text-sm font-bold text-medical-700 hover:underline dark:text-medical-300">
            &larr; <?= $view->t('articles.back') ?>
        </a>
    </p>
</article>
