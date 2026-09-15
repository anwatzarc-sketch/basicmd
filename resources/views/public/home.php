<?php
/**
 * Homepage.
 *
 * Every section below is driven by CMS data. The prototype's hardcoded
 * JavaScript arrays are gone.
 *
 * @var \Aster\Presentation\View\View                  $view
 * @var \Aster\Domain\Enum\Locale                      $locale
 * @var list<\Aster\Domain\Entity\MedicalService>      $services
 * @var list<\Aster\Domain\Entity\Doctor>              $doctors
 * @var list<\Aster\Domain\Entity\HealthPackage>       $packages
 * @var list<\Aster\Domain\Entity\Facility>            $facilities
 * @var list<\Aster\Domain\Entity\Article>             $articles
 * @var list<array{question:string, answer:string}>    $faqs
 */

declare(strict_types=1);

$t         = $view->translator;
$phone     = $settings->string('phone_primary', '');
$emergency = $settings->string('phone_emergency', $phone);
?>

<!-- ============================ HERO ============================ -->
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(15,143,137,.18),transparent_38%),linear-gradient(135deg,#f7fffe,#eef8f7)]" aria-hidden="true"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
        <div>
            <span class="eyebrow"><?= $view->t('hero.eyebrow') ?></span>

            <h1 class="mt-5 text-4xl font-extrabold leading-[1.08] tracking-[-0.035em] text-medical-900 sm:text-5xl lg:text-6xl">
                <?= $view->t('hero.title') ?>
                <span class="block text-medical-600"><?= $view->t('hero.title_accent') ?></span>
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">
                <?= $view->t('hero.lead') ?>
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="<?= $view->url('book') ?>" class="btn-primary btn-lg">
                    <?= $view->t('hero.cta_book') ?>
                </a>
                <?php if ($phone !== ''): ?>
                    <a href="tel:<?= $view->e($phone) ?>" class="btn-secondary btn-lg">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <?= $view->t('hero.cta_call') ?>
                    </a>
                <?php endif; ?>
            </div>

            <dl class="mt-10 grid max-w-xl grid-cols-3 gap-5 border-t border-slate-200/80 pt-7">
                <div>
                    <dt class="sr-only"><?= $view->t('hero.stat_specialists') ?></dt>
                    <dd>
                        <strong class="text-2xl font-extrabold text-medical-800"><?= count($doctors) ?>+</strong>
                        <span class="mt-0.5 block text-xs font-semibold text-slate-500"><?= $view->t('hero.stat_specialists') ?></span>
                    </dd>
                </div>
                <div>
                    <dt class="sr-only"><?= $view->t('hero.stat_services') ?></dt>
                    <dd>
                        <strong class="text-2xl font-extrabold text-medical-800"><?= count($services) ?></strong>
                        <span class="mt-0.5 block text-xs font-semibold text-slate-500"><?= $view->t('hero.stat_services') ?></span>
                    </dd>
                </div>
                <div>
                    <dt class="sr-only"><?= $view->t('hero.stat_emergency') ?></dt>
                    <dd>
                        <strong class="text-2xl font-extrabold text-medical-800">24/7</strong>
                        <span class="mt-0.5 block text-xs font-semibold text-slate-500"><?= $view->t('hero.stat_emergency') ?></span>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Hero card: the four featured services, from the CMS. -->
        <div class="relative hidden lg:block">
            <div class="animate-float overflow-hidden rounded-[2rem] bg-medical-900 p-3 shadow-2xl shadow-medical-900/25">
                <div class="rounded-[1.5rem] bg-white/10 p-6 backdrop-blur-sm lg:p-8">
                    <div class="mb-6 flex items-center justify-between">
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold tracking-wide text-white">
                            <?= $view->t('hero.badge') ?>
                        </span>
                        <span class="text-xl text-teal-200" aria-hidden="true">&#10022;</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach (array_slice($services, 0, 4) as $service): ?>
                            <div class="rounded-2xl bg-white p-4 shadow-sm">
                                <div class="mb-3 text-2xl" aria-hidden="true"><?= $view->e($service->icon) ?></div>
                                <strong class="block text-sm font-extrabold text-medical-900">
                                    <?= $view->e($service->title($locale)) ?>
                                </strong>
                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">
                                    <?= $view->excerpt($service->summary($locale), 48) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="absolute -bottom-5 -left-4 flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-teal-50 text-lg font-bold text-teal-700" aria-hidden="true">&#10003;</div>
                <div>
                    <strong class="block text-sm font-bold text-medical-900"><?= $view->t('hero.trust') ?></strong>
                    <span class="text-xs text-slate-500"><?= $view->t('hero.trust_sub') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================ ABOUT ============================ -->
<section id="about" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
    <div class="grid gap-10 lg:grid-cols-2 lg:gap-12">
        <div>
            <span class="eyebrow"><?= $view->t('about.eyebrow') ?></span>
            <h2 class="section-title"><?= $view->t('about.title') ?></h2>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="soft-card">
                    <b class="text-base text-medical-900"><?= $view->t("about.point_{$i}_title") ?></b>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= $view->t("about.point_{$i}_body") ?></p>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- =========================== SERVICES =========================== -->
<section id="services" class="border-y border-slate-200/60 bg-white py-16 lg:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <span class="eyebrow"><?= $view->t('services.eyebrow') ?></span>
                <h2 class="section-title"><?= $view->t('services.title') ?></h2>
            </div>
            <a href="<?= $view->url('services') ?>" class="flex items-center gap-1 font-bold text-medical-700 transition hover:text-medical-600">
                <span><?= $view->t('common.view_all') ?></span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($services, 0, 9) as $service): ?>
                <article class="service-card">
                    <div>
                        <div class="service-icon" aria-hidden="true"><?= $view->e($service->icon) ?></div>
                        <h3 class="mt-4 text-xl font-extrabold text-medical-900">
                            <a href="<?= $view->url('services/' . $service->slug) ?>" class="hover:text-medical-600">
                                <?= $view->e($service->title($locale)) ?>
                            </a>
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">
                            <?= $view->excerpt($service->summary($locale), 110) ?>
                        </p>
                    </div>
                    <div class="mt-5 flex items-center justify-between">
                        <?php if ($service->hasPrice()): ?>
                            <span class="text-sm font-bold text-medical-700">
                                <?= $view->e($t->money($service->price)) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-bold uppercase tracking-wide text-slate-400">
                                <?= $view->e($service->category->label()) ?>
                            </span>
                        <?php endif; ?>
                        <a href="<?= $view->url('book?service=' . $service->id) ?>"
                           class="text-xs font-bold text-medical-600 hover:underline">
                            <?= $view->t('services.book_this') ?> &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =========================== DOCTORS =========================== -->
<section id="doctors" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <span class="eyebrow"><?= $view->t('doctors.eyebrow') ?></span>
            <h2 class="section-title"><?= $view->t('doctors.title') ?></h2>
        </div>
        <a href="<?= $view->url('doctors') ?>" class="flex items-center gap-1 font-bold text-medical-700 transition hover:text-medical-600">
            <span><?= $view->t('common.view_all') ?></span><span aria-hidden="true">&rarr;</span>
        </a>
    </div>

    <div class="doctor-grid">
        <?php foreach (array_slice($doctors, 0, 4) as $doctor): ?>
            <?php $photo = $view->media($doctor->photoPath); ?>
            <article class="doctor-card">
                <div class="doctor-card__photo-wrap">
                    <?php if ($photo !== null): ?>
                        <img class="doctor-card__photo" src="<?= $photo ?>"
                             alt="<?= $view->e($doctor->name($locale)) ?>"
                             loading="lazy" width="108" height="108">
                    <?php else: ?>
                        <span class="doctor-card__photo--fallback" aria-hidden="true">
                            <?= $view->e($doctor->displayInitials()) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <span class="doctor-card__badge"><?= $view->e($doctor->specialtyLabel($locale)) ?></span>

                <h3 class="doctor-card__name">
                    <a href="<?= $view->url('doctors/' . $doctor->slug) ?>" class="hover:text-medical-600 dark:hover:text-medical-300">
                        <?= $view->e($doctor->name($locale)) ?>
                    </a>
                </h3>

                <?php if ($doctor->credentials !== null || $doctor->experienceYears > 0): ?>
                    <span class="doctor-card__role">
                        <?= $view->e($doctor->credentials ?? '') ?>
                        <?php if ($doctor->credentials !== null && $doctor->experienceYears > 0): ?>&middot;<?php endif; ?>
                        <?php if ($doctor->experienceYears > 0): ?>
                            <?= $view->t($doctor->experienceYears === 1 ? 'doctors.experience_one' : 'doctors.experience', ['years' => $doctor->experienceYears]) ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>

                <?php if ($doctor->biography($locale) !== null): ?>
                    <p class="doctor-card__bio"><?= $view->excerpt($doctor->biography($locale), 130) ?></p>
                <?php endif; ?>

                <?php if ($doctor->isBookable()): ?>
                    <a href="<?= $view->url('book?doctor=' . $doctor->id) ?>"
                       class="mt-5 inline-block text-sm font-bold text-medical-700 transition hover:text-medical-600 dark:text-medical-300 dark:hover:text-medical-200">
                        <?= $view->t('services.cta') ?> &rarr;
                    </a>
                <?php else: ?>
                    <span class="badge <?= $view->e($doctor->status->badgeClass()) ?> mt-5">
                        <?= $view->t($doctor->status->translationKey()) ?>
                    </span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- ========================== FACILITIES ========================== -->
<?php if ($facilities !== []): ?>
<section id="facilities" class="relative overflow-hidden bg-medical-900 py-16 text-white lg:py-20">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(15,143,137,.25),transparent_42%)]" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <span class="eyebrow-invert"><?= $view->t('facilities.eyebrow') ?></span>
        <h2 class="mt-3 max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">
            <?= $view->t('facilities.title') ?>
        </h2>

        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($facilities, 0, 6) as $index => $facility): ?>
                <div class="facility-card">
                    <span class="text-xs font-bold text-teal-300"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h3 class="mt-4 text-lg font-extrabold"><?= $view->e($facility->title($locale)) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/65">
                        <?= $view->excerpt($facility->summary($locale), 130) ?>
                    </p>
                    <?php if ($facility->status->value !== 'operational'): ?>
                        <span class="badge mt-3 border-white/20 bg-white/10 text-white/80">
                            <?= $view->t($facility->status->translationKey()) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================== PACKAGES ========================== -->
<?php if ($packages !== []): ?>
<section id="packages" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
    <div class="rounded-[2rem] bg-gradient-to-br from-medical-50 via-teal-50/30 to-white p-6 shadow-sm ring-1 ring-medical-100/80 sm:p-10 lg:p-12">
        <div class="grid items-start gap-10 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <span class="eyebrow"><?= $view->t('packages.eyebrow') ?></span>
                <h2 class="section-title"><?= $view->t('packages.title') ?></h2>
                <p class="section-lead"><?= $view->t('packages.lead') ?></p>
                <a href="<?= $view->url('packages') ?>" class="btn-secondary mt-6">
                    <?= $view->t('common.view_all') ?>
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach (array_slice($packages, 0, 2) as $package): ?>
                    <div class="package-card">
                        <?php if ($package->badge !== null): ?>
                            <span class="absolute -top-3 right-5 rounded-full bg-gold px-3 py-1 text-[10px] font-extrabold uppercase tracking-wide text-white">
                                <?= $view->e($package->badge) ?>
                            </span>
                        <?php endif; ?>

                        <b class="text-lg text-medical-900"><?= $view->e($package->name($locale)) ?></b>
                        <strong class="mt-1 block text-2xl font-extrabold text-medical-600">
                            <?= $view->e($t->money($package->price)) ?>
                        </strong>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">
                            <?= $view->excerpt($package->summary($locale), 100) ?>
                        </p>

                        <?php $items = $package->itemList($locale); ?>
                        <?php if ($items !== []): ?>
                            <ul class="mt-4 grid gap-1.5 text-xs text-slate-600">
                                <?php foreach (array_slice($items, 0, 4) as $item): ?>
                                    <li class="flex gap-2">
                                        <span class="mt-0.5 text-medical-500" aria-hidden="true">&#10003;</span>
                                        <span><?= $view->e($item) ?></span>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($items) > 4): ?>
                                    <li class="pl-5 font-semibold text-medical-600">
                                        +<?= count($items) - 4 ?> more
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="<?= $view->url('book?package=' . $package->id) ?>" class="btn-primary btn-sm mt-5 w-full">
                            <?= $view->t('packages.book') ?>
                        </a>

                        <?php if ($package->requiresDeposit()): ?>
                            <p class="mt-2 text-center text-[11px] text-slate-500">
                                <?= $view->t('packages.deposit_note', [
                                    'percent' => $package->depositPercentLabel(),
                                    'amount'  => $t->money($package->depositAmount()),
                                ]) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========================== ARTICLES ========================== -->
<?php if ($articles !== []): ?>
<section id="articles" class="border-t border-slate-200/60 bg-white py-16 lg:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <span class="eyebrow"><?= $view->t('articles.eyebrow') ?></span>
                <h2 class="section-title"><?= $view->t('articles.title') ?></h2>
            </div>
            <a href="<?= $view->url('health') ?>" class="flex items-center gap-1 font-bold text-medical-700 transition hover:text-medical-600">
                <span><?= $view->t('common.view_all') ?></span><span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            <?php foreach ($articles as $article): ?>
                <article class="article-card">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-medical-600">
                            <?= $view->e($article->category) ?>
                        </span>
                        <h3 class="mt-2 text-lg font-extrabold leading-snug text-medical-900">
                            <a href="<?= $view->url('health/' . $article->slug) ?>" class="hover:text-medical-600">
                                <?= $view->e($article->heading($locale)) ?>
                            </a>
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            <?= $view->excerpt($article->summary($locale), 120) ?>
                        </p>
                    </div>
                    <div class="mt-6 flex items-center justify-between text-xs">
                        <span class="text-slate-400">
                            <?= $view->t('articles.read_time', ['minutes' => $article->estimatedReadMinutes($locale)]) ?>
                        </span>
                        <a href="<?= $view->url('health/' . $article->slug) ?>" class="font-bold text-medical-700 hover:underline">
                            <?= $view->t('articles.read') ?> &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ FAQ ============================ -->
<?php if ($faqs !== []): ?>
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <span class="eyebrow">FAQ</span>
    <h2 class="section-title text-3xl"><?= $view->t('faq.title') ?></h2>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        <?php foreach ($faqs as $faq): ?>
            <details class="faq group">
                <summary class="flex items-center justify-between gap-3">
                    <span><?= $view->e($faq['question']) ?></span>
                    <svg class="h-4 w-4 shrink-0 text-medical-600 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= $view->e($faq['answer']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================ CTA ============================ -->
<section class="bg-medical-50/60 py-14">
    <div class="mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 text-center sm:px-6 lg:flex-row lg:justify-between lg:px-8 lg:text-left">
        <div>
            <h2 class="text-2xl font-extrabold text-medical-900 sm:text-3xl"><?= $view->t('booking.title') ?></h2>
            <p class="mt-2 max-w-xl text-slate-600"><?= $view->t('booking.lead') ?></p>
        </div>
        <div class="flex flex-wrap justify-center gap-3">
            <a href="<?= $view->url('book') ?>" class="btn-primary btn-lg"><?= $view->t('nav.book') ?></a>
            <?php if ($emergency !== ''): ?>
                <a href="tel:<?= $view->e($emergency) ?>" class="btn-secondary btn-lg"><?= $view->t('hero.cta_call') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
