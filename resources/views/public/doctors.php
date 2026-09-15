<?php
/**
 * Doctor directory with specialty filtering.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale $locale
 * @var list<\Aster\Domain\Entity\Doctor> $doctors
 * @var list<string> $specialties
 * @var array{specialty:?string, q:?string} $filters
 */

declare(strict_types=1);
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <span class="eyebrow"><?= $view->t('doctors.eyebrow') ?></span>
    <h1 class="section-title"><?= $view->t('doctors.title') ?></h1>

    <form method="get" class="mt-8 flex flex-wrap items-center gap-3" role="search">
        <label class="sr-only" for="q"><?= $view->t('doctors.search') ?></label>
        <input class="input max-w-xs" type="search" id="q" name="q"
               placeholder="<?= $view->e($view->tRaw('doctors.search')) ?>"
               value="<?= $view->e($filters['q'] ?? '') ?>">
        <button class="btn-secondary btn-sm" type="submit"><?= $view->t('form.search') ?></button>
    </form>

    <div class="mt-5 flex flex-wrap gap-2">
        <a href="<?= $view->url('doctors') ?>" class="chip <?= ($filters['specialty'] ?? null) === null ? 'chip-active' : '' ?>">
            <?= $view->t('doctors.all') ?>
        </a>
        <?php foreach ($specialties as $specialty): ?>
            <a href="<?= $view->url('doctors?specialty=' . rawurlencode($specialty)) ?>"
               class="chip <?= ($filters['specialty'] ?? null) === $specialty ? 'chip-active' : '' ?>">
                <?= $view->e($specialty) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($doctors === []): ?>
        <p class="mt-12 rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
            <?= $view->t('doctors.none') ?>
        </p>
    <?php else: ?>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($doctors as $doctor): ?>
                <article class="doctor-card">
                    <?php $photo = $view->media($doctor->photoPath); ?>
                    <?php if ($photo !== null): ?>
                        <img src="<?= $photo ?>" alt="<?= $view->e($doctor->name($locale)) ?>"
                             class="h-48 w-full object-cover" loading="lazy" width="320" height="192">
                    <?php else: ?>
                        <div class="doctor-avatar" aria-hidden="true"><?= $view->e($doctor->displayInitials()) ?></div>
                    <?php endif; ?>

                    <div class="p-5">
                        <h2 class="text-lg font-extrabold text-medical-900">
                            <a href="<?= $view->url('doctors/' . $doctor->slug) ?>" class="hover:text-medical-600">
                                <?= $view->e($doctor->name($locale)) ?>
                            </a>
                        </h2>
                        <p class="mt-0.5 text-sm font-semibold text-medical-600">
                            <?= $view->e($doctor->specialtyLabel($locale)) ?>
                        </p>

                        <?php if ($doctor->credentials !== null): ?>
                            <p class="mt-2 text-xs font-medium text-slate-500"><?= $view->e($doctor->credentials) ?></p>
                        <?php endif; ?>

                        <?php if ($doctor->experienceYears > 0): ?>
                            <p class="mt-1 text-xs text-slate-500">
                                <?= $view->t(
                                    $doctor->experienceYears === 1 ? 'doctors.experience_one' : 'doctors.experience',
                                    ['years' => $doctor->experienceYears],
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($doctor->isBookable()): ?>
                            <a href="<?= $view->url('book?doctor=' . $doctor->id) ?>" class="btn-primary btn-sm mt-4 w-full">
                                <?= $view->t('nav.book') ?>
                            </a>
                        <?php else: ?>
                            <span class="badge <?= $view->e($doctor->status->badgeClass()) ?> mt-4">
                                <?= $view->t($doctor->status->translationKey()) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
