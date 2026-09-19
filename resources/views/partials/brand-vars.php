<?php
/**
 * Emits the resolved brand palette as CSS custom properties on :root.
 *
 * Always emits the full set - BrandResolver has already defaulted every
 * value, so this partial never has to guess. The nonce is mandatory: the
 * CSP sets style-src with only 'nonce-...' permitted, so this tag is the
 * only way brand colours reach the page - there must never be an inline
 * style="..." attribute carrying one instead.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string $cspNonce
 */

declare(strict_types=1);

$brand = $view->brand;
?>
<style nonce="<?= $view->e($cspNonce ?? '') ?>">:root{
<?php foreach (\MediCareMini\Infrastructure\Support\BrandPalette::SHADES as $shade): ?>
--brand-<?= $shade ?>: <?= $view->e($brand->primary($shade)) ?>;
<?php endforeach; ?>
--brand-secondary: <?= $view->e($brand->secondaryColor) ?>;
--brand-background: <?= $view->e($brand->backgroundColor) ?>;
--brand-surface: <?= $view->e($brand->surface) ?>;
--brand-transparency: <?= $view->e((string) $brand->transparency) ?>;
--brand-header-text: <?= $view->e($brand->headerText) ?>;
--brand-body-text: <?= $view->e($brand->bodyText) ?>;
--brand-footer-bg: <?= $view->e($brand->footerBackground) ?>;
--brand-footer-text: <?= $view->e($brand->footerText) ?>;
}</style>
