<?php
/**
 * Patient registration (FRS 10.1). Submits to
 * PatientDeduplicationService::findOrRegister() - matched-existing and
 * genuinely-new are both handled the same form; the result page says
 * which one happened.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Enum\Gender> $genders
 * @var list<\MediCareMini\Domain\Enum\BloodGroup> $bloodGroups
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);

$val = static fn (string $k): string => $old[$k] ?? '';
?>
<form method="post" action="<?= $view->adminUrl('patients') ?>" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <div class="alert-info" role="note">
        <span>
            Searching first is always faster than registering - a phone number or national ID
            that already exists will be matched automatically, not duplicated.
        </span>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="first_name">First name <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="first_name" name="first_name" required value="<?= $view->e($val('first_name')) ?>">
        </div>
        <div class="field">
            <label class="label" for="last_name">Last name <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="text" id="last_name" name="last_name" required value="<?= $view->e($val('last_name')) ?>">
        </div>
        <div class="field">
            <label class="label" for="date_of_birth">Date of birth <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="date" id="date_of_birth" name="date_of_birth" required
                   max="<?= (new DateTimeImmutable())->format('Y-m-d') ?>" value="<?= $view->e($val('date_of_birth')) ?>">
        </div>
        <div class="field">
            <label class="label" for="gender">Gender <span class="text-rose-500" aria-hidden="true">*</span></label>
            <select class="select" id="gender" name="gender" required>
                <option value="">Select...</option>
                <?php foreach ($genders as $gender): ?>
                    <option value="<?= $view->e($gender->value) ?>"><?= $view->e($gender->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="phone_number">Phone number <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input" type="tel" id="phone_number" name="phone_number" required
                   placeholder="0911 123 456" value="<?= $view->e($val('phone_number')) ?>">
        </div>
        <div class="field">
            <label class="label" for="national_id">National ID</label>
            <input class="input" type="text" id="national_id" name="national_id" value="<?= $view->e($val('national_id')) ?>">
        </div>
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= $view->e($val('email')) ?>">
        </div>
        <div class="field">
            <label class="label" for="blood_group">Blood group</label>
            <select class="select" id="blood_group" name="blood_group">
                <option value="">Unknown</option>
                <?php foreach ($bloodGroups as $group): ?>
                    <option value="<?= $view->e($group->value) ?>"><?= $view->e($group->value) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field sm:col-span-2">
            <label class="label" for="address">Address</label>
            <textarea class="textarea" id="address" name="address" rows="2"><?= $view->e($val('address')) ?></textarea>
        </div>
        <div class="field">
            <label class="label" for="emergency_contact_name">Emergency contact name</label>
            <input class="input" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= $view->e($val('emergency_contact_name')) ?>">
        </div>
        <div class="field">
            <label class="label" for="emergency_contact_phone">Emergency contact phone</label>
            <input class="input" type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $view->e($val('emergency_contact_phone')) ?>">
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary">Save</button>
        <a href="<?= $view->adminUrl('patients') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
