<?php
/**
 * Patient portal sign-in (FRS 10.6).
 *
 * @var \Aster\Presentation\View\View $view
 * @var string $return
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);
?>
<div class="text-center">
    <?php if ($view->brand->logoImage !== null): ?>
        <img src="<?= $view->media($view->brand->logoImage) ?>" alt=""
             class="mx-auto h-14 w-14 rounded-2xl object-cover shadow-brand">
    <?php else: ?>
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-medical-700 text-2xl font-extrabold text-white shadow-brand"><?= $view->e($view->brand->businessInitials) ?></span>
    <?php endif; ?>
    <h1 class="mt-5 text-2xl font-extrabold text-medical-900"><?= $view->e($view->brand->businessName) ?></h1>
    <p class="mt-1 text-sm text-slate-500">Patient portal</p>
</div>

<form method="post" action="<?= $view->url('patient/portal/login') ?>" class="card mt-8 grid gap-5 p-6 sm:p-8">
    <?= $view->csrfField() ?>
    <?php if ($return !== ''): ?>
        <input type="hidden" name="return" value="<?= $view->e($return) ?>">
    <?php endif; ?>

    <div class="field">
        <label class="label" for="pid">Patient ID</label>
        <input class="input font-mono <?= isset($errors['pid']) ? 'input-error' : '' ?>"
               type="text" id="pid" name="pid" autocomplete="username"
               required autofocus placeholder="PID-2026-00001" value="<?= $view->e($old['pid'] ?? '') ?>">
        <?php if (isset($errors['pid'])): ?>
            <span class="field-error"><?= $view->e($errors['pid']) ?></span>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="label" for="password">Password</label>
        <input class="input" type="password" id="password" name="password"
               autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn-primary w-full">Sign in</button>

    <p class="text-center text-xs leading-relaxed text-slate-500">
        Your Patient ID and portal password are given to you by clinic staff.
        Lost either one? Ask at the front desk.
    </p>
</form>

<p class="mt-6 text-center text-xs text-slate-400">
    <a class="hover:text-medical-700" href="<?= $view->url('') ?>">&larr; Back to the website</a>
</p>
