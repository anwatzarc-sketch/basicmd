<?php
/**
 * Patient portal layout (FRS 10.6).
 *
 * Deliberately its own, much smaller layout rather than a reuse of
 * layouts/admin - a patient is never staff, has no sidebar of
 * permission-gated admin links to see, and nothing here should look like
 * it belongs to the staff-facing application.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\Patient|null $patient
 * @var string $content
 * @var array $meta
 * @var array $flash
 * @var string $cspNonce
 */

declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= $view->e($locale->htmlLang()) ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $view->e(($meta['title'] ?? 'My Portal') . ' | ' . $view->brand->businessName) ?></title>
    <meta name="csrf-token" content="<?= $view->e($view->csrfToken()) ?>">
    <meta name="theme-color" content="rgb(<?= $view->e($view->brand->primary('700')) ?>)">
    <link rel="icon" href="<?= $view->asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= $view->url('manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= $view->asset('assets/img/icons/apple-touch-icon.png') ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <?php if ($view->config->assetsBuilt()): ?>
        <link rel="stylesheet" href="<?= $view->asset('dist/css/app.min.css') ?>">
        <?= $view->partial('partials/brand-vars', ['view' => $view, 'cspNonce' => $cspNonce]) ?>
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script nonce="<?= $view->e($cspNonce) ?>">
            tailwind.config = { theme: { extend: { colors: { medical: {
                50:'#effcfb',100:'#d8f7f4',200:'#b3ede8',300:'#82ded7',400:'#4bc4bd',
                500:'#0f8f89',600:'#087b77',700:'#056460',800:'#0c4a47',900:'#063b3a',950:'#032423'
            } } } } };
        </script>
    <?php endif; ?>
</head>
<body class="h-full bg-slate-100 font-sans antialiased">

<a class="skip-link" href="#portal-main">Skip to content</a>

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
        <a href="<?= $view->url('patient/portal/dashboard') ?>" class="flex items-center gap-3">
            <?php if ($view->brand->logoImage !== null): ?>
                <img src="<?= $view->media($view->brand->logoImage) ?>" alt=""
                     class="h-9 w-9 rounded-xl object-cover">
            <?php else: ?>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-medical-700 text-base font-extrabold text-white"><?= $view->e($view->brand->businessInitials) ?></span>
            <?php endif; ?>
            <span class="leading-tight">
                <strong class="block text-sm font-extrabold text-medical-900"><?= $view->e($view->brand->businessName) ?></strong>
                <small class="text-[10px] font-bold uppercase tracking-[0.16em] text-medical-600">My Portal</small>
            </span>
        </a>

        <?php if ($patient !== null): ?>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <p class="text-xs font-bold text-slate-900"><?= $view->e($patient->fullName()) ?></p>
                    <p class="font-mono text-[10px] text-slate-500"><?= $view->e($patient->pid->value) ?></p>
                </div>
                <form method="post" action="<?= $view->url('patient/portal/logout') ?>">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn-secondary btn-sm">Sign out</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>

<main id="portal-main" class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <?= $view->partial('partials/flash', ['view' => $view, 'flash' => $flash ?? []]) ?>
    <?= $content ?>
</main>

<script nonce="<?= $view->e($cspNonce) ?>" src="<?= $view->asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
