<?php
/**
 * Doctor create/edit form, including capacity and leave.
 *
 * The capacity fields on this page directly control the public booking
 * engine: raising slot_capacity widens availability immediately.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Doctor|null $doctor
 * @var list<array<string,mixed>> $timeOff
 * @var list<\Aster\Domain\Enum\DoctorStatus> $statuses
 * @var array<string,string> $old
 */

declare(strict_types=1);

$t      = $view->translator;
$action = $doctor === null
    ? $view->adminUrl('doctors')
    : $view->adminUrl('doctors/' . $doctor->id);

$val = static fn (string $key, mixed $current = ''): string
    => (string) ($old[$key] ?? $current ?? '');
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="card-pad grid gap-5">
        <?= $view->csrfField() ?>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field">
                <label class="label" for="full_name">Full name <span class="text-rose-500" aria-hidden="true">*</span></label>
                <input class="input" type="text" id="full_name" name="full_name" required
                       value="<?= $view->e($val('full_name', $doctor?->fullName)) ?>">
            </div>

            <div class="field">
                <label class="label" for="full_name_am">Full name (Amharic)</label>
                <input class="input font-ethiopic" type="text" id="full_name_am" name="full_name_am"
                       lang="am-ET" value="<?= $view->e($val('full_name_am', $doctor?->fullNameAm)) ?>">
            </div>

            <div class="field">
                <label class="label" for="specialty">Specialty <span class="text-rose-500" aria-hidden="true">*</span></label>
                <input class="input" type="text" id="specialty" name="specialty" required
                       value="<?= $view->e($val('specialty', $doctor?->specialty)) ?>">
            </div>

            <div class="field">
                <label class="label" for="specialty_am">Specialty (Amharic)</label>
                <input class="input font-ethiopic" type="text" id="specialty_am" name="specialty_am"
                       lang="am-ET" value="<?= $view->e($val('specialty_am', $doctor?->specialtyAm)) ?>">
            </div>

            <div class="field">
                <label class="label" for="credentials">Credentials</label>
                <input class="input" type="text" id="credentials" name="credentials"
                       placeholder="MD, Internal Medicine"
                       value="<?= $view->e($val('credentials', $doctor?->credentials)) ?>">
            </div>

            <div class="field">
                <label class="label" for="experience_years">Years of experience</label>
                <input class="input" type="number" id="experience_years" name="experience_years" min="0" max="70"
                       value="<?= $view->e($val('experience_years', (string) ($doctor?->experienceYears ?? 0))) ?>">
            </div>

            <div class="field">
                <label class="label" for="phone">Phone</label>
                <input class="input" type="tel" id="phone" name="phone"
                       value="<?= $view->e($val('phone', $doctor?->phone)) ?>">
            </div>

            <div class="field">
                <label class="label" for="initials">Initials</label>
                <input class="input" type="text" id="initials" name="initials" maxlength="4"
                       placeholder="Auto-generated if blank"
                       value="<?= $view->e($val('initials', $doctor?->initials)) ?>">
            </div>

            <div class="field sm:col-span-2">
                <label class="label" for="bio">Biography (English)</label>
                <textarea class="textarea" id="bio" name="bio" rows="4"><?= $view->e($val('bio', $doctor?->bio)) ?></textarea>
            </div>

            <div class="field sm:col-span-2">
                <label class="label" for="bio_am">Biography (Amharic)</label>
                <textarea class="textarea font-ethiopic" id="bio_am" name="bio_am" rows="4"
                          lang="am-ET"><?= $view->e($val('bio_am', $doctor?->bioAm)) ?></textarea>
            </div>

            <div class="field sm:col-span-2">
                <label class="label" for="photo">Photo</label>
                <input class="input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <span class="hint">Converted to WebP and resized automatically. JPG, PNG or WebP.</span>
                <?php if ($doctor?->hasPhoto()): ?>
                    <img src="<?= $view->media($doctor->photoPath) ?>" alt=""
                         class="mt-3 h-24 w-24 rounded-2xl object-cover" width="96" height="96">
                <?php endif; ?>
            </div>
        </div>

        <!-- Capacity: the booking engine's control surface. -->
        <fieldset class="grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-3">
            <legend class="mb-1 text-sm font-extrabold uppercase tracking-wider text-medical-700">
                Availability &amp; pricing
            </legend>

            <div class="field">
                <label class="label" for="daily_capacity">Appointments per day</label>
                <input class="input" type="number" id="daily_capacity" name="daily_capacity" min="1" max="100"
                       value="<?= $view->e($val('daily_capacity', (string) ($doctor?->dailyCapacity ?? 16))) ?>">
            </div>

            <div class="field">
                <label class="label" for="slot_capacity">Per two-hour slot</label>
                <input class="input" type="number" id="slot_capacity" name="slot_capacity" min="1" max="50"
                       value="<?= $view->e($val('slot_capacity', (string) ($doctor?->slotCapacity ?? 4))) ?>">
            </div>

            <div class="field">
                <label class="label" for="consultation_fee">Consultation fee (ETB)</label>
                <input class="input" type="number" id="consultation_fee" name="consultation_fee" step="0.01" min="0"
                       value="<?= $view->e($val('consultation_fee', $doctor?->consultationFee->toDatabase() ?? '0.00')) ?>">
                <span class="hint">Overrides the service list price when set.</span>
            </div>
        </fieldset>

        <div class="grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-2">
            <div class="field">
                <label class="label" for="status">Status</label>
                <select class="select" id="status" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $view->e($status->value) ?>"
                            <?= $view->attr(($doctor?->status->value ?? 'active') === $status->value, 'selected') ?>>
                            <?= $view->e($status->label()) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">"On leave" keeps the profile visible but blocks bookings.</span>
            </div>

            <div class="field">
                <label class="label" for="sort_order">Display order</label>
                <input class="input" type="number" id="sort_order" name="sort_order"
                       value="<?= $view->e($val('sort_order', (string) ($doctor?->sortOrder ?? 0))) ?>">
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary"><?= $doctor === null ? 'Add doctor' : 'Save changes' ?></button>
            <a href="<?= $view->adminUrl('doctors') ?>" class="btn-secondary">Cancel</a>
        </div>
    </form>

    <!-- Leave: dates recorded here are removed from availability entirely. -->
    <?php if ($doctor !== null): ?>
        <aside class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Leave</h2>
            <p class="mt-1 text-xs text-slate-500">
                Dates recorded here are closed for booking and hidden from the availability grid.
            </p>

            <form method="post" action="<?= $view->adminUrl('doctors/' . $doctor->id . '/leave') ?>" class="mt-4 grid gap-3">
                <?= $view->csrfField() ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="starts_on">From</label>
                        <input class="input" type="date" id="starts_on" name="starts_on" required>
                    </div>
                    <div class="field">
                        <label class="label" for="ends_on">To</label>
                        <input class="input" type="date" id="ends_on" name="ends_on" required>
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="reason">Reason</label>
                    <input class="input" type="text" id="reason" name="reason" placeholder="Optional">
                </div>
                <button type="submit" class="btn-secondary btn-sm">Add leave</button>
            </form>

            <?php if ($timeOff !== []): ?>
                <ul class="mt-5 grid gap-2 border-t border-slate-200 pt-4">
                    <?php foreach ($timeOff as $leave): ?>
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <b class="block text-slate-800">
                                    <?= $view->e((string) $leave['starts_on']) ?> &rarr; <?= $view->e((string) $leave['ends_on']) ?>
                                </b>
                                <?php if (!empty($leave['reason'])): ?>
                                    <span class="truncate text-xs text-slate-500"><?= $view->e((string) $leave['reason']) ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="post"
                                  action="<?= $view->adminUrl('doctors/' . $doctor->id . '/leave/' . (int) $leave['id'] . '/delete') ?>"
                                  data-confirm="Remove this leave period?">
                                <?= $view->csrfField() ?>
                                <button type="submit" class="btn-ghost btn-sm text-rose-600">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>
