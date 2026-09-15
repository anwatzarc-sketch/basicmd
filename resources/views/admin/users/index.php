<?php
/**
 * Staff account list.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User $user       the signed-in administrator
 * @var list<\Aster\Domain\Entity\User> $users
 * @var list<\Aster\Domain\Enum\UserRole> $roles
 * @var list<\Aster\Domain\Enum\UserStatus> $statuses
 * @var array<string,int> $counts
 * @var array $filters
 */

declare(strict_types=1);

$t = $view->translator;
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3" data-auto-filter>
        <div class="field">
            <label class="label" for="role">Role</label>
            <select class="select" id="role" name="role">
                <option value="">All roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $view->e($role->value) ?>" <?= $view->attr(($filters['role'] ?? '') === $role->value, 'selected') ?>>
                        <?= $view->e($role->label()) ?> (<?= (int) ($counts[$role->value] ?? 0) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $view->e($status->value) ?>" <?= $view->attr(($filters['status'] ?? '') === $status->value, 'selected') ?>>
                        <?= $view->e($status->label()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <a href="<?= $view->adminUrl('users/create') ?>" class="btn-primary btn-sm">Add account</a>
</div>

<div class="card mt-6">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $account): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                                    <?= $view->e($account->initials()) ?>
                                </span>
                                <div class="min-w-0">
                                    <b class="block truncate text-slate-900"><?= $view->e($account->fullName) ?></b>
                                    <?php if ($account->id === $user->id): ?>
                                        <span class="text-[11px] font-bold text-medical-600">That is you</span>
                                    <?php elseif ($account->doctorId !== null): ?>
                                        <span class="text-[11px] text-slate-500">Linked to a doctor profile</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-slate-600"><?= $view->e($account->email) ?></td>
                        <td><span class="badge <?= $view->e($account->role->badgeClass()) ?>"><?= $view->e($account->role->label()) ?></span></td>
                        <td>
                            <span class="badge <?= $view->e($account->status->badgeClass()) ?>"><?= $view->e($account->status->label()) ?></span>
                            <?php if ($account->isLocked()): ?>
                                <span class="badge border-rose-200 bg-rose-50 text-rose-700">Locked</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs text-slate-500">
                            <?= $account->lastLoginAt !== null ? $view->e($t->relative($account->lastLoginAt)) : 'Never' ?>
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <a href="<?= $view->adminUrl('users/' . $account->id . '/edit') ?>" class="btn-ghost btn-sm">Edit</a>

                                <?php if ($account->isLocked()): ?>
                                    <form method="post" action="<?= $view->adminUrl('users/' . $account->id . '/unlock') ?>">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn-ghost btn-sm text-medical-700">Unlock</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="<?= $view->adminUrl('users/' . $account->id . '/reset-password') ?>"
                                      data-confirm="Issue a new temporary password for <?= $view->e($account->fullName) ?>?">
                                    <?= $view->csrfField() ?>
                                    <button type="submit" class="btn-ghost btn-sm">Reset password</button>
                                </form>

                                <?php if ($account->id !== $user->id): ?>
                                    <form method="post" action="<?= $view->adminUrl('users/' . $account->id . '/delete') ?>"
                                          data-confirm="Delete this account? They will lose access immediately.">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn-ghost btn-sm text-rose-600">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
