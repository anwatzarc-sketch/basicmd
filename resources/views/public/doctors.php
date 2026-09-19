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
        <p class="empty-state mt-12">
            <?= $view->t('doctors.none') ?>
        </p>
    <?php else: ?>
        <div class="doctor-grid">
            <?php foreach ($doctors as $doctor): ?>
                <?php $photo = $view->media($doctor->photoPath); ?>
                <article class="doctor-card">
                    <div class="doctor-card__photo-wrap">
                        <?php if ($photo !== null): ?>
                            <img class="doctor-card__photo" src="<?= $photo ?>"
                                 alt="<?= $view->e($doctor->name($locale)) ?>"
                                 loading="lazy" width="108" height="108">
                        <?php else: ?>
                            <span class="doctor-card__photo--fallback">
                                <?= $view->partial('partials/doctor-avatar', ['view' => $view]) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <span class="doctor-card__badge"><?= $view->e($doctor->specialtyLabel($locale)) ?></span>

                    <h2 class="doctor-card__name">
                        <a href="<?= $view->url('doctors/' . $doctor->slug) ?>" class="hover:text-medical-600 dark:hover:text-medical-300">
                            <?= $view->e($doctor->name($locale)) ?>
                        </a>
                    </h2>

                    <?php if ($doctor->credentials !== null || $doctor->experienceYears > 0): ?>
                        <span class="doctor-card__role">
                            <?= $view->e($doctor->credentials ?? '') ?>
                            <?php if ($doctor->credentials !== null && $doctor->experienceYears > 0): ?>&middot;<?php endif; ?>
                            <?php if ($doctor->experienceYears > 0): ?>
                                <?= $view->t(
                                    $doctor->experienceYears === 1 ? 'doctors.experience_one' : 'doctors.experience',
                                    ['years' => $doctor->experienceYears],
                                ) ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($doctor->biography($locale) !== null): ?>
                        <p class="doctor-card__bio"><?= $view->excerpt($doctor->biography($locale), 150) ?></p>
                    <?php endif; ?>

                    <?php if ($doctor->isBookable()): ?>
                        <a href="<?= $view->url('book?doctor=' . $doctor->id) ?>" class="btn-primary btn-sm mt-5 w-full">
                            <?= $view->t('nav.book') ?>
                        </a>
                    <?php else: ?>
                        <span class="badge <?= $view->e($doctor->status->badgeClass()) ?> mt-5">
                            <?= $view->t($doctor->status->translationKey()) ?>
                        </span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="cta-panel-dark mt-16 flex flex-wrap items-center justify-between gap-6">
        <div class="max-w-2xl">
            <span class="eyebrow-invert"><?= $view->t('booking.eyebrow') ?></span>
            <?php /* text-white, not the class default: this panel is dark in
                     both themes, so .section-title's slate-900 would be
                     dark-on-dark in light mode. */ ?>
            <h2 class="section-title text-white dark:text-white"><?= $view->t('booking.title') ?></h2>
            <p class="mt-2 text-sm leading-relaxed text-white/75"><?= $view->t('booking.lead') ?></p>
        </div>
        <a href="<?= $view->url('book') ?>" class="btn-invert btn-lg"><?= $view->t('nav.book') ?></a>
    </div>
</section>
