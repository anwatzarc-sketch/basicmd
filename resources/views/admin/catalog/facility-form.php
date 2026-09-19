<?php
/**
 * Facility create/edit form.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Facility|null $facility
 * @var list<\Aster\Domain\Enum\FacilityStatus> $statuses
 * @var array<string,string> $old
 */

declare(strict_types=1);

$action = $facility === null
    ? $view->adminUrl('facilities')
    : $view->adminUrl('facilities/' . $facility->id);

$val = static fn (string $key, mixed $current = ''): string => (string) ($old[$key] ?? $current ?? '');
?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="name">Name (English) <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="name" name="fac_name" required
                   value="<?= $view->e($val('fac_name', $facility?->name)) ?>">
        </div>

        <div class="field">
            <label class="label" for="name_am">Name (Amharic)</label>
            <input class="input font-ethiopic" type="text" id="name_am" name="name_am" lang="am-ET"
                   value="<?= $view->e($val('name_am', $facility?->nameAm)) ?>">
        </div>

        <div class="field">
            <label class="label" for="name_om">Name (Afaan Oromoo)</label>
            <input class="input" type="text" id="name_om" name="name_om" lang="om-ET"
                   value="<?= $view->e($val('name_om', $facility?->nameOm)) ?>">
        </div>

        <div class="field">
            <label class="label" for="type">Type</label>
            <input class="input" type="text" id="type" name="type"
                   placeholder="Clinical Room, Radiology, Laboratory"
                   value="<?= $view->e($val('type', $facility?->type ?? 'Clinical Room')) ?>">
        </div>

        <div class="field">
            <label class="label" for="room_label">Room / location</label>
            <input class="input" type="text" id="room_label" name="room_label"
                   value="<?= $view->e($val('room_label', $facility?->roomLabel)) ?>">
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="description">Description (English)</label>
            <textarea class="textarea" id="description" name="description" rows="3"><?= $view->e($val('description', $facility?->description)) ?></textarea>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="description_am">Description (Amharic)</label>
            <textarea class="textarea font-ethiopic" id="description_am" name="description_am" rows="3"
                      lang="am-ET"><?= $view->e($val('description_am', $facility?->descriptionAm)) ?></textarea>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="description_om">Description (Afaan Oromoo)</label>
            <textarea class="textarea" id="description_om" name="description_om" rows="3"
                      lang="om-ET"><?= $view->e($val('description_om', $facility?->descriptionOm)) ?></textarea>
        </div>

        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $view->e($status->value) ?>"
                        <?= $view->attr(($facility?->status->value ?? 'operational') === $status->value, 'selected') ?>>
                        <?= $view->e($status->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint">"Closed" hides the facility from the public site entirely.</span>
        </div>

        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order"
                   value="<?= $view->e($val('sort_order', (string) ($facility?->sortOrder ?? 0))) ?>">
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="notes">Internal notes</label>
            <input class="input" type="text" id="notes" name="notes" placeholder="Not shown publicly"
                   value="<?= $view->e($val('notes', $facility?->notes)) ?>">
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="image">Photo</label>
            <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if ($facility?->imagePath !== null): ?>
                <img src="<?= $view->media($facility->imagePath) ?>" alt=""
                     class="mt-3 h-28 w-44 rounded-2xl object-cover" width="176" height="112">
            <?php endif; ?>
        </div>

        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700 sm:col-span-2">
            <input class="checkbox" type="checkbox" name="is_public" value="1"
                   <?= $view->attr($facility?->isPublic ?? true, 'checked') ?>>
            Show on the public website
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $facility === null ? 'Add facility' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('facilities') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
