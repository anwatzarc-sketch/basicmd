<?php
/**
 * Service create/edit form.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\MedicalService|null $service
 * @var list<\Aster\Domain\Enum\ServiceCategory> $categories
 * @var array<string,string> $old
 */

declare(strict_types=1);

$action = $service === null
    ? $view->adminUrl('services')
    : $view->adminUrl('services/' . $service->id);

$val = static fn (string $k, mixed $c = ''): string => (string) ($old[$k] ?? $c ?? '');
?>
<form method="post" action="<?= $action ?>" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="name">Name (English) <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="name" name="ser_name" required value="<?= $view->e($val('ser_name', $service?->name)) ?>">
        </div>
        <div class="field">
            <label class="label" for="name_am">Name (Amharic)</label>
            <input class="input font-ethiopic" type="text" id="name_am" name="name_am" lang="am-ET"
                   value="<?= $view->e($val('name_am', $service?->nameAm)) ?>">
        </div>
        <div class="field">
            <label class="label" for="icon">Icon</label>
            <input class="input" type="text" id="icon" name="icon" maxlength="8" placeholder="An emoji, e.g. a stethoscope"
                   value="<?= $view->e($val('icon', $service?->icon)) ?>">
        </div>
        <div class="field">
            <label class="label" for="category">Category</label>
            <select class="select" id="category" name="category">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $view->e($category->value) ?>"
                        <?= $view->attr(($service?->category->value ?? 'clinical') === $category->value, 'selected') ?>>
                        <?= $view->e($category->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="price">Price (ETB)</label>
            <input class="input" type="number" id="price" name="price" step="0.01" min="0"
                   value="<?= $view->e($val('price', $service?->price->toDatabase() ?? '0.00')) ?>">
            <span class="hint">Leave at 0 for services with no fixed fee.</span>
        </div>
        <div class="field">
            <label class="label" for="duration_min">Duration (minutes)</label>
            <input class="input" type="number" id="duration_min" name="duration_min" min="5" max="480"
                   value="<?= $view->e($val('duration_min', (string) ($service?->durationMinutes ?? 30))) ?>">
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="description">Description (English)</label>
            <textarea class="textarea" id="description" name="description" rows="3"><?= $view->e($val('description', $service?->description)) ?></textarea>
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="description_am">Description (Amharic)</label>
            <textarea class="textarea font-ethiopic" id="description_am" name="description_am" rows="3"
                      lang="am-ET"><?= $view->e($val('description_am', $service?->descriptionAm)) ?></textarea>
        </div>
        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order"
                   value="<?= $view->e($val('sort_order', (string) ($service?->sortOrder ?? 0))) ?>">
        </div>
        <div class="grid content-end gap-3">
            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <input class="checkbox" type="checkbox" name="active" value="1"
                       <?= $view->attr($service?->isActive ?? true, 'checked') ?>>
                Active (visible on the website)
            </label>
            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <input class="checkbox" type="checkbox" name="is_featured" value="1"
                       <?= $view->attr($service?->isFeatured ?? false, 'checked') ?>>
                Featured on the homepage
            </label>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $service === null ? 'Add service' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('services') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
