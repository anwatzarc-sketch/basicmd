<?php
/**
 * Print layout - a document, not a screen.
 *
 * Deliberately its own layout rather than layouts/admin with things
 * hidden: an A4 result sheet has no sidebar, no page header and no
 * navigation to suppress, and "render the admin shell then display:none
 * most of it" is how a stray 3mm of chrome ends up on a clinical report
 * that a patient keeps.
 *
 * What DOES render here and nowhere else is the action bar - it carries
 * .no-print, so it exists on screen and is absent from paper.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User|null $user
 * @var string $content
 * @var array $meta
 * @var array $flash
 * @var string $cspNonce
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
    <title><?= $view->e(($meta['title'] ?? 'Report') . ' | ' . $view->brand->businessName) ?></title>
    <meta name="csrf-token" content="<?= $view->e($view->csrfToken()) ?>">
    <link rel="icon" href="<?= $view->asset('assets/img/favicon.svg') ?>" type="image/svg+xml">

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
<body class="min-h-full bg-slate-200 font-sans antialiased print:bg-white">

<a class="skip-link" href="#print-main">Skip to content</a>

<div class="no-print">
    <?= $view->partial('partials/flash', ['view' => $view, 'flash' => $flash ?? []]) ?>
</div>

<main id="print-main" class="py-6 print:py-0">
    <?= $content ?>
</main>

<script nonce="<?= $view->e($cspNonce) ?>" src="<?= $view->asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
