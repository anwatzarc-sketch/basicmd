<?php
/**
 * Change own password.
 *
 * @var \Aster\Presentation\View\View $view
 * @var bool $forced
 * @var array<string,string> $errors
 */

declare(strict_types=1);

use Aster\Infrastructure\Security\PasswordHasher;
?>
<div class="mx-auto max-w-lg">
    <?php if ($forced): ?>
        <div class="alert-warning mb-6" role="alert">
            <span>Your account uses a temporary password. Please choose a new one before continuing.</span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $view->adminUrl('password') ?>" class="card-pad grid gap-5">
        <?= $view->csrfField() ?>

        <div class="field">
            <label class="label" for="current_password">Current password</label>
            <input class="input <?= isset($errors['current_password']) ? 'input-error' : '' ?>"
                   type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
            <?php if (isset($errors['current_password'])): ?>
                <span class="field-error"><?= $view->e($errors['current_password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input <?= isset($errors['password']) ? 'input-error' : '' ?>"
                   type="password" id="password" name="password" autocomplete="new-password" required
                   minlength="<?= PasswordHasher::minimumLength() ?>">
            <span class="hint">
                At least <?= PasswordHasher::minimumLength() ?> characters, with upper and lower case,
                a number and a symbol. Avoid the clinic name or place names.
            </span>
            <?php if (isset($errors['password'])): ?>
                <span class="field-error"><?= $view->e($errors['password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Confirm new password</label>
            <input class="input <?= isset($errors['password_confirmation']) ? 'input-error' : '' ?>"
                   type="password" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password" required>
            <?php if (isset($errors['password_confirmation'])): ?>
                <span class="field-error"><?= $view->e($errors['password_confirmation']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-primary w-full">Update password</button>
    </form>
</div>
