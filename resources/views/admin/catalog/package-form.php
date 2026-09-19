<?php
/**
 * Health package create/edit form.
 *
 * Bullet lists are edited as newline-separated textareas rather than a
 * repeater widget - far quicker for staff, and the controller splits them.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\HealthPackage|null $package
 * @var array<string,string> $old
 */

declare(strict_types=1);

use MediCareMini\Domain\Enum\Locale;

$action = $package === null
    ? $view->adminUrl('packages')
    : $view->adminUrl('packages/' . $package->id);

$val = static fn (string $k, mixed $c = ''): string => (string) ($old[$k] ?? $c ?? '');

$itemsEn = $old['items_en'] ?? implode("\n", $package?->itemList(Locale::EN) ?? []);
// The translated lists read the raw items array, not itemList(), so an
// untranslated language shows an empty box to fill in rather than the
// English list itemList() would fall back to.
$itemsAm = $old['items_am'] ?? implode("\n", $package?->items['am'] ?? []);
$itemsOm = $old['items_om'] ?? implode("\n", $package?->items['om'] ?? []);
?>
<form method="post" action="<?= $action ?>" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="title">Title (English) <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="title" name="title" required value="<?= $view->e($val('title', $package?->title)) ?>">
        </div>
        <div class="field">
            <label class="label" for="title_am">Title (Amharic)</label>
            <input class="input font-ethiopic" type="text" id="title_am" name="title_am" lang="am-ET"
                   value="<?= $view->e($val('title_am', $package?->titleAm)) ?>">
        </div>
        <div class="field">
            <label class="label" for="title_om">Title (Afaan Oromoo)</label>
            <input class="input" type="text" id="title_om" name="title_om" lang="om-ET"
                   value="<?= $view->e($val('title_om', $package?->titleOm)) ?>">
        </div>
        <div class="field">
            <label class="label" for="price_etb">Price (ETB) <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="number" id="price_etb" name="price_etb" step="0.01" min="0" required
                   value="<?= $view->e($val('price_etb', $package?->price->toDatabase() ?? '0.00')) ?>">
        </div>
        <div class="field">
            <label class="label" for="deposit_percent">Deposit (%)</label>
            <input class="input" type="number" id="deposit_percent" name="deposit_percent" min="0" max="100" step="1"
                   value="<?= $view->e($val('deposit_percent', (string) round(($package?->depositRate ?? 0.30) * 100))) ?>">
            <span class="hint">Percentage collected up front to hold the slot. 0 means pay in full.</span>
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="description">Description (English)</label>
            <textarea class="textarea" id="description" name="description" rows="3"><?= $view->e($val('description', $package?->description)) ?></textarea>
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="description_am">Description (Amharic)</label>
            <textarea class="textarea font-ethiopic" id="description_am" name="description_am" rows="3"
                      lang="am-ET"><?= $view->e($val('description_am', $package?->descriptionAm)) ?></textarea>
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="description_om">Description (Afaan Oromoo)</label>
            <textarea class="textarea" id="description_om" name="description_om" rows="3"
                      lang="om-ET"><?= $view->e($val('description_om', $package?->descriptionOm)) ?></textarea>
        </div>
        <div class="field">
            <label class="label" for="items_en">Included items (English)</label>
            <textarea class="textarea" id="items_en" name="items_en" rows="8"
                      placeholder="One item per line"><?= $view->e($itemsEn) ?></textarea>
            <span class="hint">One per line.</span>
        </div>
        <div class="field">
            <label class="label" for="items_am">Included items (Amharic)</label>
            <textarea class="textarea font-ethiopic" id="items_am" name="items_am" rows="8"
                      lang="am-ET" placeholder="One item per line"><?= $view->e($itemsAm) ?></textarea>
            <span class="hint">One per line, matching the English order.</span>
        </div>
        <div class="field">
            <label class="label" for="items_om">Included items (Afaan Oromoo)</label>
            <textarea class="textarea" id="items_om" name="items_om" rows="8"
                      lang="om-ET" placeholder="One item per line"><?= $view->e($itemsOm) ?></textarea>
            <span class="hint">One per line, matching the English order.</span>
        </div>
        <div class="field">
            <label class="label" for="badge">Badge</label>
            <input class="input" type="text" id="badge" name="badge" maxlength="40"
                   placeholder="e.g. Most popular" value="<?= $view->e($val('badge', $package?->badge)) ?>">
        </div>
        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order"
                   value="<?= $view->e($val('sort_order', (string) ($package?->sortOrder ?? 0))) ?>">
        </div>
        <div class="grid content-end gap-3 sm:col-span-2">
            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <input class="checkbox" type="checkbox" name="active" value="1" <?= $view->attr($package?->isActive ?? true, 'checked') ?>>
                Active (bookable on the website)
            </label>
            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <input class="checkbox" type="checkbox" name="is_featured" value="1" <?= $view->attr($package?->isFeatured ?? false, 'checked') ?>>
                Highlight on the homepage
            </label>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $package === null ? 'Add package' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('packages') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
