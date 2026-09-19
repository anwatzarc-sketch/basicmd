<?php
/**
 * Single service page.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale $locale
 * @var \Aster\Domain\Entity\MedicalService $service
 * @var list<\Aster\Domain\Entity\Doctor> $doctors
 * @var list<\Aster\Domain\Entity\MedicalService> $related
 */

declare(strict_types=1);

$t = $view->translator;
?>
<section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <nav class="text-sm font-semibold text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('') ?>"><?= $view->t('nav.home') ?></a></li>
            <li aria-hidden="true">/</li>
            <li><a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->url('services') ?>"><?= $view->t('nav.services') ?></a></li>
        </ol>
    </nav>

    <header class="mt-6 flex flex-wrap items-start gap-5">
        <div class="service-icon shrink-0 text-3xl" aria-hidden="true"><?= $view->e($service->icon) ?></div>
        <div class="min-w-0 flex-1">
            <span class="badge <?= $view->e($service->category->chipClass()) ?>">
                <?= $view->t($service->category->translationKey()) ?>
            </span>
            <h1 class="page-title mt-3">
                <?= $view->e($service->title($locale)) ?>
            </h1>
            <?php if ($service->summary($locale) !== null): ?>
                <p class="mt-4 text-lg leading-relaxed text-slate-600 dark:text-slate-300"><?= $view->e($service->summary($locale)) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="card mt-8 flex flex-wrap items-center justify-between gap-5 p-6">
        <dl class="flex flex-wrap gap-x-10 gap-y-4">
            <?php if ($service->hasPrice()): ?>
                <div>
                    <dt class="dt-label"><?= $view->t('common.from') ?></dt>
                    <dd class="mt-1 text-2xl font-extrabold text-medical-700 dark:text-medical-300"><?= $view->e($t->money($service->price)) ?></dd>
                </div>
            <?php endif; ?>
            <div>
                <dt class="dt-label"><?= $view->t('email.label_time') ?></dt>
                <dd class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100"><?= $view->e($service->durationLabel()) ?></dd>
            </div>
        </dl>
        <a href="<?= $view->url('book?service=' . $service->id) ?>" class="btn-primary btn-lg">
            <?= $view->t('services.book_this') ?>
        </a>
    </div>

    <?php if ($doctors !== []): ?>
        <section class="mt-12">
            <h2 class="subsection-title"><?= $view->t('doctors.eyebrow') ?></h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach (array_slice($doctors, 0, 4) as $doctor): ?>
                    <a href="<?= $view->url('doctors/' . $doctor->slug) ?>" class="doctor-card block">
                        <div class="doctor-avatar"><?= $view->partial('partials/doctor-avatar', ['view' => $view]) ?></div>
                        <div class="p-4">
                            <h3 class="block text-sm font-extrabold text-medical-900 dark:text-medical-100"><?= $view->e($doctor->name($locale)) ?></h3>
                            <span class="text-xs text-medical-600 dark:text-medical-300"><?= $view->e($doctor->specialtyLabel($locale)) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($related !== []): ?>
        <section class="mt-12">
            <h2 class="subsection-title"><?= $view->t('articles.related') ?></h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($related as $item): ?>
                    <?php if ($item->id === $service->id) { continue; } ?>
                    <a href="<?= $view->url('services/' . $item->slug) ?>" class="soft-card block">
                        <span class="text-2xl" aria-hidden="true"><?= $view->e($item->icon) ?></span>
                        <h3 class="mt-2 text-sm font-bold leading-snug text-medical-900 dark:text-medical-100">
                            <?= $view->e($item->title($locale)) ?>
                        </h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
