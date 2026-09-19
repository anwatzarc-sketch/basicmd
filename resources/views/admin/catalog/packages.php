<?php
/**
 * Health package management.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\HealthPackage> $packages
 */

declare(strict_types=1);

$t = $view->translator;
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <p class="text-sm text-slate-500">
        Prepaid screening products. The deposit percentage controls how much is collected up front.
    </p>
    <a href="<?= $view->adminUrl('packages/create') ?>" class="btn-primary btn-sm">Add package</a>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($packages as $package): ?>
        <article class="card-pad">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <b class="block truncate text-lg text-medical-900"><?= $view->e($package->title) ?></b>
                    <?php if ($package->titleAm !== null): ?>
                        <span class="block truncate font-ethiopic text-xs text-slate-500" lang="am-ET">
                            <?= $view->e($package->titleAm) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="badge shrink-0 <?= $package->isActive
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-slate-200 bg-slate-100 text-slate-600' ?>">
                    <?= $package->isActive ? 'Active' : 'Inactive' ?>
                </span>
            </div>

            <strong class="mt-3 block text-2xl font-extrabold text-medical-600"><?= $view->e($t->money($package->price)) ?></strong>

            <p class="mt-1 text-xs text-slate-500">
                <?= $view->e($package->depositPercentLabel()) ?> deposit
                = <?= $view->e($t->money($package->depositAmount())) ?>
            </p>

            <p class="mt-3 text-sm leading-relaxed text-slate-600"><?= $view->excerpt($package->description, 110) ?></p>

            <p class="mt-3 text-xs font-semibold text-slate-400">
                <?= count($package->itemList(\MediCareMini\Domain\Enum\Locale::EN)) ?> items listed
                <?php if (($package->items['am'] ?? []) === []): ?>
                    &middot; <span class="text-amber-600">No Amharic list</span>
                <?php endif; ?>
            </p>

            <div class="mt-5 flex gap-2 border-t border-slate-100 pt-4">
                <a href="<?= $view->adminUrl('packages/' . $package->id . '/edit') ?>" class="btn-secondary btn-sm flex-1">Edit</a>
                <form method="post" action="<?= $view->adminUrl('packages/' . $package->id . '/delete') ?>"
                      data-confirm="Remove this package?">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn-ghost btn-sm text-rose-600">Remove</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if ($packages === []): ?>
        <p class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 lg:col-span-2 xl:col-span-3">
            No packages yet.
        </p>
    <?php endif; ?>
</div>
