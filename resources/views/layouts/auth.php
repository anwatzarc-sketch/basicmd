<?php
/**
 * Minimal centred layout for the sign-in screen and error pages inside the
 * admin area. No navigation - there is nothing a signed-out visitor should
 * be able to reach from here.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string $content
 * @var array $meta
 */

declare(strict_types=1);

$meta = $meta ?? [];
?>
<!doctype html>
<html lang="<?= $view->e($locale->htmlLang()) ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $view->e(($meta['title'] ?? 'Sign in') . ' | ' . $view->brand->businessName) ?></title>
    <meta name="csrf-token" content="<?= $view->e($view->csrfToken()) ?>">
    <meta name="theme-color" content="rgb(<?= $view->e($view->brand->primary('700')) ?>)">
    <link rel="icon" href="<?= $view->asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= $view->url('manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= $view->asset('assets/img/icons/apple-touch-icon.png') ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php if ($view->config->assetsBuilt()): ?>
        <link rel="stylesheet" href="<?= $view->asset('dist/css/app.min.css') ?>">
        <?= $view->partial('partials/brand-vars', ['view' => $view, 'cspNonce' => $cspNonce ?? '']) ?>
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script nonce="<?= $view->e($cspNonce ?? '') ?>">
            tailwind.config = { theme: { extend: { colors: { medical: {
                50:'#effcfb',100:'#d8f7f4',500:'#0f8f89',600:'#087b77',
                700:'#056460',800:'#0c4a47',900:'#063b3a',950:'#032423'
            } } } } };
        </script>
    <?php endif; ?>
</head>
<body class="grid min-h-full place-items-center bg-slate-100 px-4 py-10 font-sans antialiased">
    <div class="w-full max-w-md">
        <?= $view->partial('partials/flash', ['view' => $view, 'flash' => $flash ?? []]) ?>
        <?= $content ?>
    </div>
    <script nonce="<?= $view->e($cspNonce ?? '') ?>" src="<?= $view->asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
