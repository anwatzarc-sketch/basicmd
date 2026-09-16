<?php
/**
 * Staff account create/edit.
 *
 * New accounts receive a generated temporary password shown once on screen
 * after saving. It is never emailed and never logged.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User|null $account
 * @var list<\Aster\Domain\Enum\UserRole> $roles
 * @var list<\Aster\Domain\Enum\UserStatus> $statuses
 * @var array<int,string> $doctors
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);

$action = $account === null
    ? $view->adminUrl('users')
    : $view->adminUrl('users/' . $account->id);

$val = static fn (string $k, mixed $c = ''): string => (string) ($old[$k] ?? $c ?? '');
?>
<form method="post" action="<?= $action ?>" class="card-pad mx-auto grid max-w-2xl gap-5">
    <?= $view->csrfField() ?>

    <?php if ($account === null): ?>
        <div class="alert-info" role="note">
            <span>
                A temporary password is generated and shown once after saving. Share it in person
                or by phone, not by email - the account must change it at first sign-in.
            </span>
        </div>
    <?php endif; ?>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="full_name">Full name <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input <?= isset($errors['full_name']) ? 'input-error' : '' ?>"
                   type="text" id="full_name" name="full_name" required
                   value="<?= $view->e($val('full_name', $account?->fullName)) ?>">
            <?php if (isset($errors['full_name'])): ?>
                <span class="field-error"><?= $view->e($errors['full_name']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="email">Email <span class="text-rose-500" aria-hidden="true">*</span></label>
            <input class="input <?= isset($errors['email']) ? 'input-error' : '' ?>"
                   type="email" id="email" name="email" required autocomplete="off"
                   value="<?= $view->e($val('email', $account?->email)) ?>">
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= $view->e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="phone">Phone</label>
            <input class="input" type="tel" id="phone" name="phone"
                   value="<?= $view->e($val('phone', $account?->phone)) ?>">
        </div>

        <div class="field">
            <label class="label" for="locale">Interface language</label>
            <select class="select" id="locale" name="locale">
                <option value="en" <?= $view->attr(($account?->locale->value ?? 'en') === 'en', 'selected') ?>>English</option>
                <option value="am" <?= $view->attr(($account?->locale->value ?? '') === 'am', 'selected') ?>>Amharic</option>
            </select>
        </div>

        <div class="field">
            <label class="label" for="role">Role <span class="text-rose-500" aria-hidden="true">*</span></label>
            <select class="select" id="role" name="role">
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $view->e($role->value) ?>"
                        <?= $view->attr(($account?->role?->value ?? \Aster\Domain\Enum\UserRole::RECEPTIONIST->value) === $role->value, 'selected') ?>>
                        <?= $view->e($role->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $view->e($status->value) ?>"
                        <?= $view->attr(($account?->status->value ?? 'active') === $status->value, 'selected') ?>>
                        <?= $view->e($status->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field sm:col-span-2">
            <label class="label" for="doctor_id">Linked doctor profile</label>
            <select class="select" id="doctor_id" name="doctor_id">
                <option value="">Not linked</option>
                <?php foreach ($doctors as $id => $name): ?>
                    <option value="<?= (int) $id ?>" <?= $view->attr($account?->doctorId === (int) $id, 'selected') ?>>
                        <?= $view->e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="hint">
                Required for the Physician role - the link is what restricts them to their own
                appointment queue. A physician account without one sees nothing.
            </span>
        </div>
    </div>

    <?php
    // Short blurb per role. Kept beside the role list (rather than fully
    // generated from permissions()) because a plain-English summary of "can
    // verify payments, cannot alter scheduling" reads far better than a
    // dumped permission-slug list - but every role must appear here, so a
    // role with nothing yet to say still gets an honest line rather than
    // silently vanishing from the summary.
    $roleBlurbs = [
        'super_admin'    => 'Full access to everything.',
        'physician'      => 'Their own appointment queue and clinical notes, plus article drafting.',
        'nurse'          => 'No dedicated screens yet - added ahead of the clinical documentation work.',
        'receptionist'   => 'Bookings and enquiries. Can see payments but cannot verify them.',
        'accountant'     => 'Verifies payments and reads revenue reporting. Cannot alter scheduling.',
        'lab_technician' => 'No dedicated screens yet - added ahead of the laboratory result exchange.',
    ];
    ?>
    <div class="rounded-2xl bg-slate-50 p-4">
        <b class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Role permissions</b>
        <div class="mt-3 grid gap-3 text-xs text-slate-600">
            <?php foreach ($roles as $role): ?>
                <p>
                    <b class="text-slate-800"><?= $view->e($role->label()) ?></b>
                    - <?= $view->e($roleBlurbs[$role->value] ?? 'See the permission matrix.') ?>
                </p>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $account === null ? 'Create account' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('users') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
