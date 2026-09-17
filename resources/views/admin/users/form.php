<?php
/**
 * Staff account create/edit.
 *
 * New accounts receive a generated temporary password shown once on screen
 * after saving. It is never emailed and never logged.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User|null $account
 * @var list<\Aster\Domain\Entity\Role> $roles
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
            <select class="select" id="role" name="role" required>
                <?php if ($account === null): ?>
                    <option value="" disabled <?= $view->attr(!isset($old['role']), 'selected') ?>>Choose a role&hellip;</option>
                <?php endif; ?>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $view->e($role->slug) ?>"
                        <?= $view->attr(($old['role'] ?? $account?->roleSlug) === $role->slug, 'selected') ?>>
                        <?= $view->e($role->label) ?><?= $role->isArchived() ? ' (archived)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role'])): ?>
                <span class="field-error"><?= $view->e($errors['role']) ?></span>
            <?php endif; ?>
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

    <div class="rounded-2xl bg-slate-50 p-4">
        <b class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Role permissions</b>
        <p class="mt-1 text-xs text-slate-500">
            Assign, edit or archive roles - and see exactly what each one grants - from
            <a href="<?= $view->adminUrl('roles') ?>" class="underline">Role Management</a>.
        </p>
        <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
            <?php foreach ($roles as $role): ?>
                <p>
                    <b class="text-slate-800"><?= $view->e($role->label) ?></b>
                    - <?= count($role->permissions) ?> permission<?= count($role->permissions) === 1 ? '' : 's' ?><?= $role->isArchived() ? ', archived' : '' ?>
                </p>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $account === null ? 'Create account' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('users') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
