<?php
/**
 * Role Management - create/edit (spec §4.2). No starter-template prefill
 * on create - there is no pre-existing role to prefill from, so the
 * checklist starts with nothing ticked.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Role|null $role
 * @var list<array{id:int, slug:string}> $permissions
 * @var array<string, list<string>> $groups domain label => sorted permission slugs
 */

declare(strict_types=1);

$action = $role === null
    ? $view->adminUrl('roles')
    : $view->adminUrl('roles/' . $role->id);

$permissionIdBySlug = [];
foreach ($permissions as $permission) {
    $permissionIdBySlug[$permission['slug']] = (int) $permission['id'];
}

$checked = $role?->permissions ?? [];
?>
<form method="post" action="<?= $action ?>" class="card-pad mx-auto grid max-w-3xl gap-5">
    <?= $view->csrfField() ?>

    <?php if ($role !== null): ?>
        <div class="field">
            <label class="label">Role slug</label>
            <input class="input" type="text" value="<?= $view->e($role->slug) ?>" disabled>
            <span class="hint">The internal key - fixed once created, only the label and permissions can change.</span>
        </div>
    <?php endif; ?>

    <div class="field">
        <label class="label" for="label">Role name <span class="text-rose-500" aria-hidden="true">*</span></label>
        <input class="input" type="text" id="label" name="label" required
               value="<?= $view->e($old['label'] ?? $role?->label ?? '') ?>"
               placeholder="e.g. ICU Nurse, Front Desk, Billing Clerk">
    </div>

    <div class="grid gap-4">
        <b class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Permissions</b>

        <?php foreach ($groups as $domain => $slugs): ?>
            <div class="rounded-2xl border border-slate-200 p-4">
                <div class="mb-2 flex items-center justify-between">
                    <b class="text-sm text-slate-800"><?= $view->e($domain) ?></b>
                    <button type="button" class="text-xs font-semibold text-medical-600" data-select-group="<?= $view->e($domain) ?>">
                        Select all
                    </button>
                </div>
                <div class="grid gap-2 sm:grid-cols-2" data-permission-group="<?= $view->e($domain) ?>">
                    <?php foreach ($slugs as $slug): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="permissions[]"
                                   value="<?= (int) ($permissionIdBySlug[$slug] ?? 0) ?>"
                                   <?= $view->attr(in_array($slug, $checked, true), 'checked') ?>>
                            <?= $view->e($slug) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary"><?= $role === null ? 'Create role' : 'Save changes' ?></button>
        <a href="<?= $view->adminUrl('roles') ?>" class="btn-secondary">Cancel</a>
    </div>
</form>
