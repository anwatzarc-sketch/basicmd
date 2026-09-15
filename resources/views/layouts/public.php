<?php
/**
 * Public site layout.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale     $locale
 * @var string                        $content
 * @var array                         $meta
 * @var array                         $flash
 * @var string                        $cspNonce
 */

declare(strict_types=1);

$meta       = $meta ?? [];
$settings   = $settings ?? null;
$t          = $view->translator;
$clinicName = $settings?->string('clinic_name', 'Aster Medical Center') ?? 'Aster Medical Center';

if ($locale->value === 'am' && $settings !== null) {
    $clinicName = $settings->string('clinic_name_am', $clinicName);
}
?>
<!doctype html>
<html lang="<?= $view->e($locale->htmlLang()) ?>" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#056460">

    <title><?= $view->e($meta['title'] ?? $clinicName) ?></title>
    <meta name="description" content="<?= $view->e($meta['description'] ?? '') ?>">

    <?php if (!empty($meta['noindex'])): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php else: ?>
        <meta name="robots" content="index, follow, max-image-preview:large">
    <?php endif; ?>

    <?php if (!empty($meta['canonical'])): ?>
        <link rel="canonical" href="<?= $view->e($meta['canonical']) ?>">
    <?php endif; ?>

    <?php /* hreflang tells Google the two languages are translations, not duplicates. */ ?>
    <?php foreach (($alternates ?? []) as $hreflang => $href): ?>
        <link rel="alternate" hreflang="<?= $view->e($hreflang) ?>" href="<?= $view->e($href) ?>">
    <?php endforeach; ?>

    <?php /* Open Graph, for shares on Telegram and Facebook. */ ?>
    <meta property="og:site_name" content="<?= $view->e($clinicName) ?>">
    <meta property="og:title" content="<?= $view->e($meta['title'] ?? $clinicName) ?>">
    <meta property="og:description" content="<?= $view->e($meta['description'] ?? '') ?>">
    <meta property="og:type" content="<?= $view->e($meta['type'] ?? 'website') ?>">
    <meta property="og:locale" content="<?= $view->e($locale === \Aster\Domain\Enum\Locale::AM ? 'am_ET' : 'en_US') ?>">
    <?php if (!empty($meta['canonical'])): ?>
        <meta property="og:url" content="<?= $view->e($meta['canonical']) ?>">
    <?php endif; ?>
    <?php if (!empty($meta['image'])): ?>
        <meta property="og:image" content="<?= $view->e($meta['image']) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>

    <?= $view->csrfToken() !== '' ? '<meta name="csrf-token" content="' . $view->e($view->csrfToken()) . '">' : '' ?>

    <link rel="icon" href="<?= $view->asset('assets/img/favicon.svg') ?>" type="image/svg+xml">

    <?php if ($view->config->assetsBuilt()): ?>
        <?php /* Preloading the stylesheet removes a round trip from first paint. */ ?>
        <link rel="preload" as="style" href="<?= $view->asset('dist/css/app.min.css') ?>">
        <link rel="stylesheet" href="<?= $view->asset('dist/css/app.min.css') ?>">
        <link rel="preload" as="font" type="font/woff2" href="/dist/fonts/inter-latin.woff2" crossorigin>
        <?php if ($view->isAmharic()): ?>
            <link rel="preload" as="font" type="font/woff2" href="/dist/fonts/noto-ethiopic.woff2" crossorigin>
        <?php endif; ?>
    <?php else: ?>
        <?php /* Development fallback only; never reached in production. */ ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script nonce="<?= $view->e($cspNonce) ?>">
            tailwind.config = {
                theme: { extend: { colors: { medical: {
                    50:'#effcfb',100:'#d8f7f4',200:'#b3ede8',300:'#82ded7',400:'#4bc4bd',
                    500:'#0f8f89',600:'#087b77',700:'#056460',800:'#0c4a47',900:'#063b3a',950:'#032423'
                }, gold: '#d99a32' } } }
            };
        </script>
    <?php endif; ?>

    <?php if (!empty($structuredData)): ?>
        <script type="application/ld+json" nonce="<?= $view->e($cspNonce) ?>"><?= $structuredData ?></script>
    <?php endif; ?>
</head>
<body class="<?= $view->e($locale->fontClass()) ?> bg-slate-50 text-slate-800 antialiased">

<a class="skip-link" href="#main"><?= $view->t('common.skip_to_content') ?></a>

<?= $view->partial('partials/header', ['view' => $view, 'locale' => $locale, 'settings' => $settings]) ?>

<main id="main">
    <?= $view->partial('partials/flash', ['view' => $view, 'flash' => $flash ?? []]) ?>
    <?= $content ?>
</main>

<?= $view->partial('partials/footer', ['view' => $view, 'locale' => $locale, 'settings' => $settings]) ?>

<script nonce="<?= $view->e($cspNonce) ?>" src="<?= $view->asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
