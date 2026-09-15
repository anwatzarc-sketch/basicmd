<?php
/**
 * Service catalogue management.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\MedicalService> $services
 * @var list<\Aster\Domain\Enum\ServiceCategory> $categories
 * @var array $filters
 */

declare(strict_types=1);

$t = $view->translator;
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3" data-auto-filter>
        <div class="field">
            <label class="label" for="q">Search</label>
            <input class="input" type="search" id="q" name="q" value="<?= $view->e($filters['q'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <option value="">All</option>
                <option value="active" <?= $view->attr(($filters['status'] ?? '') === 'active', 'selected') ?>>Active</option>
                <option value="inactive" <?= $view->attr(($filters['status'] ?? '') === 'inactive', 'selected') ?>>Inactive</option>
            </select>
        </div>
    </form>
    <a href="<?= $view->adminUrl('services/create') ?>" class="btn-primary btn-sm">Add service</a>
</div>

<div class="card mt-6">
    <?php if ($services === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No services yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Service</th><th>Category</th><th>Price</th><th>Duration</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="text-xl" aria-hidden="true"><?= $view->e($service->icon) ?></span>
                                    <div class="min-w-0">
                                        <b class="block truncate text-slate-900"><?= $view->e($service->name) ?></b>
                                        <?php if ($service->nameAm !== null): ?>
                                            <span class="block truncate font-ethiopic text-xs text-slate-500" lang="am-ET">
                                                <?= $view->e($service->nameAm) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs font-semibold text-amber-600">No Amharic translation</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge <?= $view->e($service->category->chipClass()) ?>"><?= $view->e($service->category->label()) ?></span></td>
                            <td class="font-semibold text-slate-700">
                                <?= $service->hasPrice() ? $view->e($t->money($service->price)) : '&mdash;' ?>
                            </td>
                            <td class="text-xs text-slate-500"><?= $view->e($service->durationLabel()) ?></td>
                            <td>
                                <span class="badge <?= $service->isActive
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-slate-100 text-slate-600' ?>">
                                    <?= $service->isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="<?= $view->adminUrl('services/' . $service->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>
                                    <form method="post" action="<?= $view->adminUrl('services/' . $service->id . '/delete') ?>"
                                          data-confirm="Remove this service?">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn-ghost btn-sm text-rose-600">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
