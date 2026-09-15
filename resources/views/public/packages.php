<?php
/**
 * Health package pricing page - the prepaid screening products.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale $locale
 * @var list<\Aster\Domain\Entity\HealthPackage> $packages
 */

declare(strict_types=1);

$t = $view->translator;
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <div class="max-w-2xl">
        <span class="eyebrow"><?= $view->t('packages.eyebrow') ?></span>
        <h1 class="section-title"><?= $view->t('packages.title') ?></h1>
        <p class="section-lead"><?= $view->t('packages.lead') ?></p>
    </div>

    <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($packages as $package): ?>
            <article class="package-card <?= $package->isFeatured ? 'ring-2 ring-medical-500' : '' ?>">
                <?php if ($package->badge !== null): ?>
                    <span class="absolute -top-3 left-6 rounded-full bg-gold px-3 py-1 text-[10px] font-extrabold uppercase tracking-wide text-white">
                        <?= $view->e($package->badge) ?>
                    </span>
                <?php endif; ?>

                <h2 class="text-xl font-extrabold text-medical-900"><?= $view->e($package->name($locale)) ?></h2>

                <div class="mt-3 flex items-baseline gap-2">
                    <strong class="text-3xl font-extrabold text-medical-600"><?= $view->e($t->money($package->price)) ?></strong>
                </div>

                <?php if ($package->summary($locale) !== null): ?>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= $view->e($package->summary($locale)) ?></p>
                <?php endif; ?>

                <?php $items = $package->itemList($locale); ?>
                <?php if ($items !== []): ?>
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <b class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
                            <?= $view->t('packages.includes') ?>
                        </b>
                        <ul class="mt-3 grid gap-2 text-sm text-slate-600">
                            <?php foreach ($items as $item): ?>
                                <li class="flex gap-2.5">
                                    <span class="mt-0.5 shrink-0 font-bold text-medical-500" aria-hidden="true">&#10003;</span>
                                    <span><?= $view->e($item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="mt-auto pt-6">
                    <a href="<?= $view->url('book?package=' . $package->id) ?>" class="btn-primary w-full">
                        <?= $view->t('packages.book') ?>
                    </a>

                    <?php if ($package->requiresDeposit()): ?>
                        <p class="mt-3 text-center text-xs leading-relaxed text-slate-500">
                            <?= $view->t('packages.deposit_note', [
                                'percent' => $package->depositPercentLabel(),
                                'amount'  => $t->money($package->depositAmount()),
                            ]) ?>
                            <span class="mt-0.5 block text-slate-400">
                                <?= $view->t('packages.pay_full', ['amount' => $t->money($package->price)]) ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="mt-12 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
        <h2 class="text-lg font-extrabold text-medical-900"><?= $view->t('faq.q5') ?></h2>
        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600"><?= $view->t('faq.a5') ?></p>
    </div>
</section>
