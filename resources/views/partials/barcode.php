<?php
/**
 * Code 39 barcode as inline SVG.
 *
 * Inline rather than an <img> to a rendering endpoint: this prints, and a
 * printed page that depends on a second HTTP request has a failure mode
 * where the report comes out of the tray with a broken-image box where
 * the specimen label should be.
 *
 * Geometry comes from Code39::bars(); everything emitted here is a
 * number, escaped like any other value.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string $value
 * @var int $height bar height in px
 */

declare(strict_types=1);

use MediCareMini\Presentation\Support\Code39;

$symbol = Code39::bars($value ?? '');
$height = (int) ($height ?? 34);

if ($symbol['bars'] === []) {
    return;
}
?>
<!--
    The height rides on the SVG's own attribute rather than a Tailwind
    class: an arbitrary-value utility built at runtime is never in the
    source Tailwind scans, so the class would simply not exist in the
    compiled stylesheet.
-->
<svg class="block w-full max-w-[16rem]"
     height="<?= $height ?>"
     viewBox="0 0 <?= (int) $symbol['width'] ?> <?= $height ?>"
     preserveAspectRatio="none"
     role="img"
     aria-label="Barcode: <?= $view->e($symbol['value']) ?>">
    <?php foreach ($symbol['bars'] as $bar): ?>
        <rect x="<?= (int) $bar['x'] ?>" y="0" width="<?= (int) $bar['width'] ?>" height="<?= $height ?>" fill="#0f172a"></rect>
    <?php endforeach; ?>
</svg>
