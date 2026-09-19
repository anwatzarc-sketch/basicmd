<?php
/**
 * Assign a role to a user, with an optional ward-scope multiselect
 * (spec §4.2/§4.5). No selection in the multiselect = unscoped = sees
 * every ward for this grant, unchanged from today.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\User> $users
 * @var list<\MediCareMini\Domain\Entity\Role> $roles (unarchived only)
 * @var list<string> $wardNames
 */

declare(strict_types=1);
?>
<div class="card-pad mx-auto grid max-w-2xl gap-6">
    <form method="post" action="<?= $view->adminUrl('roles/assign') ?>" class="grid gap-5">
        <?= $view->csrfField() ?>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field">
                <label class="label" for="user_id">Staff account <span class="text-rose-500" aria-hidden="true">*</span></label>
                <select class="select" id="user_id" name="user_id" required>
                    <option value="">Choose a user</option>
                    <?php foreach ($users as $account): ?>
                        <option value="<?= $account->id ?>"><?= $view->e($account->fullName) ?> - <?= $view->e($account->email) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="role_id">Role <span class="text-rose-500" aria-hidden="true">*</span></label>
                <select class="select" id="role_id" name="role_id" required>
                    <option value="">Choose a role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role->id ?>"><?= $view->e($role->label) ?> (<?= count($role->permissions) ?> permissions)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label class="label" for="ward_names">Ward scope (optional)</label>
            <select class="select" id="ward_names" name="ward_names[]" multiple size="6">
                <?php foreach ($wardNames as $wardName): ?>
                    <option value="<?= $view->e($wardName) ?>"><?= $view->e($wardName) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="hint">
                Leave everything unselected for an unscoped grant - sees every patient this role's
                permissions allow, exactly like today. Selecting one or more wards restricts this
                grant to patients whose active encounter is currently in one of those wards
                (spec §4.5) - a real but partial boundary, strongest for inpatient wards and
                weaker for OPD/ER.
            </span>
            <?php if ($wardNames === []): ?>
                <span class="hint text-amber-600">
                    No wards are registered yet - every grant is unscoped until at least one exists.
                    <a href="<?= $view->adminUrl('wards/create') ?>" class="underline">Register one</a>.
                </span>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary">Assign role</button>
            <a href="<?= $view->adminUrl('roles') ?>" class="btn-secondary">Back to roles</a>
        </div>
    </form>

    <div class="rounded-2xl bg-slate-50 p-4">
        <b class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Revoke a grant</b>
        <p class="mt-2 text-xs text-slate-600">
            Open the staff account's edit screen to see every role it currently holds, or submit
            below with the same user/role pair to remove one.
        </p>
        <form method="post" action="<?= $view->adminUrl('roles/revoke') ?>" class="mt-3 flex flex-wrap items-end gap-3">
            <?= $view->csrfField() ?>
            <div class="field">
                <label class="label" for="revoke_user_id">Staff account</label>
                <select class="select" id="revoke_user_id" name="user_id" required>
                    <option value="">Choose a user</option>
                    <?php foreach ($users as $account): ?>
                        <option value="<?= $account->id ?>"><?= $view->e($account->fullName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="revoke_role_id">Role</label>
                <select class="select" id="revoke_role_id" name="role_id" required>
                    <option value="">Choose a role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role->id ?>"><?= $view->e($role->label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-ghost btn-sm text-rose-600"
                    data-confirm="Revoke this role from this user? Any ward scope on the grant is removed with it.">
                Revoke
            </button>
        </form>
    </div>
</div>
