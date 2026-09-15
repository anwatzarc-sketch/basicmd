<?php
/**
 * Manual booking form, for phone and walk-in patients.
 *
 * Runs through the same BookingService as the public form, so capacity
 * limits, pricing and notifications behave identically no matter who books.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\MedicalService> $services
 * @var list<\Aster\Domain\Entity\HealthPackage> $packages
 * @var list<\Aster\Domain\Entity\Doctor> $doctors
 * @var list<\Aster\Domain\Enum\TimeSlot> $slots
 * @var list<\Aster\Domain\Enum\QueueTier> $tiers
 * @var list<\Aster\Domain\Enum\BookingSource> $sources
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);

$t   = $view->translator;
$val = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $default);
?>
<form method="post" action="<?= $view->adminUrl('appointments') ?>" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="service_id">Service</label>
            <select class="select <?= isset($errors['service_id']) ? 'input-error' : '' ?>" id="service_id" name="service_id">
                <option value="">Select a service</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= $service->id ?>" <?= $view->attr($val('service_id') === (string) $service->id, 'selected') ?>>
                        <?= $view->e($service->name) ?><?= $service->hasPrice() ? ' - ' . $view->e($t->money($service->price)) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['service_id'])): ?>
                <span class="field-error"><?= $view->e($errors['service_id']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="package_id">Health package</label>
            <select class="select" id="package_id" name="package_id">
                <option value="">None</option>
                <?php foreach ($packages as $package): ?>
                    <option value="<?= $package->id ?>" <?= $view->attr($val('package_id') === (string) $package->id, 'selected') ?>>
                        <?= $view->e($package->title) ?> - <?= $view->e($t->money($package->price)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="doctor_id">Doctor</label>
            <select class="select" id="doctor_id" name="doctor_id">
                <option value="">Any available doctor</option>
                <?php foreach ($doctors as $doctor): ?>
                    <option value="<?= $doctor->id ?>" <?= $view->attr($val('doctor_id') === (string) $doctor->id, 'selected') ?>>
                        <?= $view->e($doctor->fullName) ?> - <?= $view->e($doctor->specialty) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="source">Booked via</label>
            <select class="select" id="source" name="source">
                <?php foreach ($sources as $source): ?>
                    <option value="<?= $view->e($source->value) ?>" <?= $view->attr($val('source', 'phone') === $source->value, 'selected') ?>>
                        <?= $view->e($source->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="appointment_date">Date <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input <?= isset($errors['appointment_date']) ? 'input-error' : '' ?>"
                   type="date" id="appointment_date" name="appointment_date"
                   min="<?= $view->e($minDate) ?>" value="<?= $view->e($val('appointment_date')) ?>" required>
            <?php if (isset($errors['appointment_date'])): ?>
                <span class="field-error"><?= $view->e($errors['appointment_date']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="time_slot">Time <span class="text-rose-500" aria-hidden="true">*</span></label>
            <select class="select <?= isset($errors['time_slot']) ? 'input-error' : '' ?>" id="time_slot" name="time_slot" required>
                <option value="">Select a time</option>
                <?php foreach ($slots as $slot): ?>
                    <option value="<?= $view->e($slot->value) ?>" <?= $view->attr($val('time_slot') === $slot->value, 'selected') ?>>
                        <?= $view->e($slot->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['time_slot'])): ?>
                <span class="field-error"><?= $view->e($errors['time_slot']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="patient_name">Patient name <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input <?= isset($errors['patient_name']) ? 'input-error' : '' ?>"
                   type="text" id="patient_name" name="patient_name" required
                   value="<?= $view->e($val('patient_name')) ?>">
            <?php if (isset($errors['patient_name'])): ?>
                <span class="field-error"><?= $view->e($errors['patient_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="patient_phone">Phone <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input <?= isset($errors['patient_phone']) ? 'input-error' : '' ?>"
                   type="tel" id="patient_phone" name="patient_phone" required
                   placeholder="09XX XXX XXX" value="<?= $view->e($val('patient_phone')) ?>">
            <?php if (isset($errors['patient_phone'])): ?>
                <span class="field-error"><?= $view->e($errors['patient_phone']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="patient_email">Email</label>
            <input class="input <?= isset($errors['patient_email']) ? 'input-error' : '' ?>"
                   type="email" id="patient_email" name="patient_email"
                   value="<?= $view->e($val('patient_email')) ?>">
            <span class="hint">Needed for the confirmation and the 24-hour reminder.</span>
            <?php if (isset($errors['patient_email'])): ?>
                <span class="field-error"><?= $view->e($errors['patient_email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field sm:col-span-2">
            <span class="label">Queue</span>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ($tiers as $tier): ?>
                    <label class="radio-card">
                        <input type="radio" name="queue_tier" value="<?= $view->e($tier->value) ?>" class="radio mt-0.5"
                               <?= $view->attr($val('queue_tier', 'standard') === $tier->value, 'checked') ?>>
                        <span class="text-sm font-bold text-medical-900"><?= $view->e($tier->label()) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="patient_notes">Notes</label>
            <textarea class="textarea" id="patient_notes" name="patient_notes" rows="3"><?= $view->e($val('patient_notes')) ?></textarea>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary">Create appointment</button>
        <a href="<?= $view->adminUrl('appointments') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
