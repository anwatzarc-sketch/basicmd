<?php
/**
 * Services catalogue with server-side filtering.
 *
 * Filters are links, not JavaScript state, so each filtered view is a real
 * crawlable URL that can rank on its own.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Enum\Locale $locale
 * @var list<\MediCareMini\Domain\Entity\MedicalService> $services
 * @var array<string,int> $categories
 * @var array{category:?string, q:?string} $filters
 */

declare(strict_types=1);

use MediCareMini\Domain\Enum\ServiceCategory;

$t = $view->translator;
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <span class="eyebrow"><?= $view->t('services.eyebrow') ?></span>
    <h1 class="section-title"><?= $view->t('services.title') ?></h1>

    <form method="get" class="mt-8 flex flex-wrap items-center gap-3" role="search">
        <label class="sr-only" for="q"><?= $view->t('services.search') ?></label>
        <input class="input max-w-xs" type="search" id="q" name="q"
               placeholder="<?= $view->e($view->tRaw('services.search')) ?>"
               value="<?= $view->e($filters['q'] ?? '') ?>">
        <?php if (($filters['category'] ?? null) !== null): ?>
            <input type="hidden" name="category" value="<?= $view->e($filters['category']) ?>">
        <?php endif; ?>
        <button class="btn-secondary btn-sm" type="submit"><?= $view->t('form.search') ?></button>
    </form>

    <div class="mt-5 flex flex-wrap gap-2">
        <a href="<?= $view->url('services') ?>"
           class="chip <?= ($filters['category'] ?? null) === null ? 'chip-active' : '' ?>">
            <?= $view->t('services.all') ?>
        </a>
        <?php foreach (ServiceCategory::all() as $category): ?>
            <?php $count = $categories[$category->value] ?? 0; ?>
            <?php if ($count === 0) { continue; } ?>
            <a href="<?= $view->url('services?category=' . $category->value) ?>"
               class="chip <?= ($filters['category'] ?? null) === $category->value ? 'chip-active' : '' ?>">
                <?= $view->t($category->translationKey()) ?>
                <span class="opacity-60"><?= $count ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($services === []): ?>
        <p class="empty-state mt-12">
            <?= $view->t('services.none') ?>
        </p>
    <?php else: ?>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($services as $service): ?>
                <article class="service-card">
                    <div>
                        <div class="service-icon" aria-hidden="true"><?= $view->e($service->icon) ?></div>
                        <h2 class="subsection-title mt-4">
                            <a href="<?= $view->url('services/' . $service->slug) ?>" class="hover:text-medical-600 dark:hover:text-medical-300">
                                <?= $view->e($service->title($locale)) ?>
                            </a>
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            <?= $view->excerpt($service->summary($locale), 130) ?>
                        </p>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <span class="badge <?= $view->e($service->category->chipClass()) ?>">
                            <?= $view->t($service->category->translationKey()) ?>
                        </span>
                        <?php if ($service->hasPrice()): ?>
                            <span class="text-sm font-bold text-medical-700 dark:text-medical-300"><?= $view->e($t->money($service->price)) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= $view->url('book?service=' . $service->id) ?>" class="btn-primary btn-sm mt-4 w-full">
                        <?= $view->t('services.book_this') ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
