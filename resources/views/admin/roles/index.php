<?php
/**
 * Role Management - list (spec §4.2).
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var list<\MediCareMini\Domain\Entity\Role> $roles
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <p class="text-sm text-slate-500">
        Every role beyond Super Administrator is created here - no code change or deploy
        is required to add one.
    </p>
    <div class="flex gap-2">
        <a href="<?= $view->adminUrl('roles/assign') ?>" class="btn-secondary btn-sm">Assign role to user</a>
        <a href="<?= $view->adminUrl('roles/create') ?>" class="btn-primary btn-sm">Create role</a>
    </div>
</div>

<div class="card mt-6">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Role</th><th>Permissions</th><th>Assigned users</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><b class="text-slate-900"><?= $view->e($role->label) ?></b></td>
                        <td class="text-slate-600"><?= count($role->permissions) ?></td>
                        <td class="text-slate-600"><?= $role->userCount ?></td>
                        <td>
                            <?php if ($role->isArchived()): ?>
                                <span class="badge border-slate-200 bg-slate-50 text-slate-600">Archived</span>
                            <?php else: ?>
                                <span class="badge border-teal-200 bg-teal-50 text-teal-700">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <?php if ($role->slug !== 'super_admin'): ?>
                                    <a href="<?= $view->adminUrl('roles/' . $role->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>

                                    <?php if ($role->isArchived()): ?>
                                        <form method="post" action="<?= $view->adminUrl('roles/' . $role->id . '/unarchive') ?>">
                                            <?= $view->csrfField() ?>
                                            <button type="submit" class="btn-ghost btn-sm text-medical-700">Restore</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= $view->adminUrl('roles/' . $role->id . '/archive') ?>"
                                              data-confirm="Archive <?= $view->e($role->label) ?>? It stays visible on any existing assignment but cannot be assigned to anyone new.">
                                            <?= $view->csrfField() ?>
                                            <button type="submit" class="btn-ghost btn-sm text-rose-600">Archive</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Seeded - always holds every permission</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
