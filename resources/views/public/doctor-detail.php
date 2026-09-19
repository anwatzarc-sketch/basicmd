<?php
/**
 * Doctor profile page.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Enum\Locale $locale
 * @var \MediCareMini\Domain\Entity\Doctor $doctor
 * @var list<\MediCareMini\Domain\Entity\Article> $articles
 */

declare(strict_types=1);

$t     = $view->translator;
$photo = $view->media($doctor->photoPath);
?>
<section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <nav class="text-sm font-semibold text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('') ?>"><?= $view->t('nav.home') ?></a></li>
            <li aria-hidden="true">/</li>
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('doctors') ?>"><?= $view->t('nav.doctors') ?></a></li>
        </ol>
    </nav>

    <header class="mt-6 grid gap-8 sm:grid-cols-[200px_1fr]">
        <div class="overflow-hidden rounded-3xl border border-slate-200 dark:border-slate-800">
            <?php if ($photo !== null): ?>
                <img src="<?= $photo ?>" alt="<?= $view->e($doctor->name($locale)) ?>"
                     class="h-56 w-full object-cover sm:h-52" width="200" height="208">
            <?php else: ?>
                <div class="doctor-avatar h-56 sm:h-52"><?= $view->partial('partials/doctor-avatar', ['view' => $view]) ?></div>
            <?php endif; ?>
        </div>

        <div>
            <h1 class="page-title"><?= $view->e($doctor->name($locale)) ?></h1>
            <p class="mt-1 text-lg font-semibold text-medical-600 dark:text-medical-300"><?= $view->e($doctor->specialtyLabel($locale)) ?></p>

            <div class="mt-4 flex flex-wrap gap-2">
                <?php if ($doctor->credentials !== null): ?>
                    <span class="badge border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"><?= $view->e($doctor->credentials) ?></span>
                <?php endif; ?>
                <?php if ($doctor->experienceYears > 0): ?>
                    <span class="badge border-medical-100 bg-medical-50 text-medical-700 dark:border-medical-800 dark:bg-medical-950/40 dark:text-medical-300">
                        <?= $view->t($doctor->experienceYears === 1 ? 'doctors.experience_one' : 'doctors.experience', ['years' => $doctor->experienceYears]) ?>
                    </span>
                <?php endif; ?>
                <span class="badge <?= $view->e($doctor->status->badgeClass()) ?>">
                    <?= $view->t($doctor->status->translationKey()) ?>
                </span>
            </div>

            <?php if ($doctor->isBookable()): ?>
                <a href="<?= $view->url('book?doctor=' . $doctor->id) ?>" class="btn-primary mt-6">
                    <?= $view->t('doctors.book_with', ['name' => $doctor->name($locale)]) ?>
                </a>
            <?php else: ?>
                <p class="alert-warning mt-6">
                    <?= $view->t('doctors.on_leave') ?>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($doctor->biography($locale) !== null): ?>
        <div class="prose-article mt-10">
            <p><?= nl2br($view->e($doctor->biography($locale))) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($doctor->consultationFee->isPositive()): ?>
        <div class="card mt-8 flex flex-wrap items-center justify-between gap-4 p-6">
            <div>
                <span class="dt-label"><?= $view->t('common.from') ?></span>
                <strong class="mt-1 block text-2xl font-extrabold text-medical-700 dark:text-medical-300">
                    <?= $view->e($t->money($doctor->consultationFee)) ?>
                </strong>
            </div>
            <a href="<?= $view->url('book?doctor=' . $doctor->id) ?>" class="btn-primary"><?= $view->t('nav.book') ?></a>
        </div>
    <?php endif; ?>

    <?php if ($articles !== []): ?>
        <section class="mt-12">
            <h2 class="subsection-title"><?= $view->t('articles.eyebrow') ?></h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <?php foreach ($articles as $article): ?>
                    <a href="<?= $view->url('health/' . $article->slug) ?>"
                       class="article-card block hover:border-medical-500">
                        <span class="text-xs font-extrabold uppercase tracking-wider text-medical-600 dark:text-medical-300">
                            <?= $view->e($article->category) ?>
                        </span>
                        <h3 class="mt-2 text-sm font-bold leading-snug text-medical-900 dark:text-medical-100">
                            <?= $view->e($article->heading($locale)) ?>
                        </h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
