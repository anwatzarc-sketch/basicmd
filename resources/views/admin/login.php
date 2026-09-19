<?php
/**
 * Staff sign-in.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string $return
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);
?>
<div class="text-center">
    <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-medical-700 text-2xl font-extrabold text-white shadow-brand">A</span>
    <h1 class="mt-5 text-2xl font-extrabold text-medical-900">MediCareMini</h1>
    <p class="mt-1 text-sm text-slate-500">Staff portal</p>
</div>

<form method="post" action="<?= $view->adminUrl('login') ?>" class="card mt-8 grid gap-5 p-6 sm:p-8">
    <?= $view->csrfField() ?>
    <?php if ($return !== ''): ?>
        <input type="hidden" name="return" value="<?= $view->e($return) ?>">
    <?php endif; ?>

    <div class="field">
        <label class="label" for="email">Email address</label>
        <input class="input <?= isset($errors['email']) ? 'input-error' : '' ?>"
               type="email" id="email" name="email" autocomplete="username"
               required autofocus value="<?= $view->e($old['email'] ?? '') ?>">
        <?php if (isset($errors['email'])): ?>
            <span class="field-error"><?= $view->e($errors['email']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password"
               autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn-primary w-full">Sign in</button>

    <p class="text-center text-xs leading-relaxed text-slate-500">
        Lost your password? Ask an administrator to reset it for you.
    </p>
</form>

<p class="mt-6 text-center text-xs text-slate-400">
    <a class="hover:text-medical-700" href="<?= $view->url('') ?>">&larr; Back to the website</a>
</p>
