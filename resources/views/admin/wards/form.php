<?php
/**
 * Ward Locations - register/edit a bed (spec §4.5).
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\WardLocation|null $ward
 */

declare(strict_types=1);

$action = $ward === null
    ? $view->adminUrl('wards')
    : $view->adminUrl('wards/' . $ward->id);

$val = static fn (string $k, mixed $c = ''): string => (string) ($old[$k] ?? $c ?? '');
?>
<form method="post" action="<?= $action ?>" class="card-pad mx-auto grid max-w-xl gap-5">
    <?= $view->csrfField() ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="ward_name">Ward name <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="ward_name" name="ward_name" required
                   value="<?= $view->e($val('ward_name', $ward?->wardName)) ?>" placeholder="e.g. ICU, General Ward A">
            <span class="hint">This is the value Role Management's ward-scope multiselect offers.</span>
        </div>

        <div class="field">
            <label class="label" for="room_number">Room number <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="room_number" name="room_number" required
                   value="<?= $view->e($val('room_number', $ward?->roomNumber)) ?>">
        </div>

        <div class="field">
            <label class="label" for="bed_number">Bed number <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="bed_number" name="bed_number" required
                   value="<?= $view->e($val('bed_number', $ward?->bedNumber)) ?>">
        </div>

        <div class="field">
            <label class="label" for="daily_rate">Daily rate</label>
            <input class="input" type="number" step="0.01" min="0" id="daily_rate" name="daily_rate"
                   value="<?= $view->e($val('daily_rate', $ward?->dailyRate->toMajor())) ?>">
        </div>

        <div class="field sm:col-span-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_transient" value="1"
                       <?= $view->attr(($ward?->isTransient ?? false), 'checked') ?>>
                Transient (consultation/waiting bay - never an admission target)
            </label>
            <span class="hint">
                A transient location does not carry the same operational meaning as an inpatient
                ward - ward scoping protects an admission bed cleanly and a transient location
                weakly (spec §4.5).
            </span>
        </div>
    </div>

    <?php if ($ward !== null): ?>
        <div class="alert-info" role="note">
            <span>
                Occupancy is set automatically on admission/discharge and is not editable from
                this screen.
            </span>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $ward === null ? 'Register bed' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('wards') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
