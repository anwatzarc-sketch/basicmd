<?php
/**
 * Public site header: brand, navigation, language switcher, mobile menu.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale     $locale
 */

declare(strict_types=1);

use Aster\Domain\Enum\Locale;

$settings   = $settings ?? null;
$clinicName = $settings?->string('clinic_name', 'Aster Medical') ?? 'Aster Medical';

if ($locale === Locale::AM && $settings !== null) {
    $clinicName = $settings->string('clinic_name_am', $clinicName);
}

$emergency = $settings?->string('phone_emergency', '') ?? '';
$current   = $_SERVER['REQUEST_URI'] ?? '/';

$nav = [
    ['path' => '/services',  'key' => 'nav.services'],
    ['path' => '/doctors',   'key' => 'nav.doctors'],
    ['path' => '/packages',  'key' => 'nav.packages'],
    ['path' => '/health',    'key' => 'nav.articles'],
    ['path' => '/locations', 'key' => 'nav.locations'],
    ['path' => '/contact',   'key' => 'nav.contact'],
];

/** Preserve the active language when switching pages. */
$withLang = static fn (string $path, string $lang): string => $path . '?lang=' . $lang;

$isActive = static fn (string $path): bool => str_starts_with(parse_url($current, PHP_URL_PATH) ?? '/', $path);
?>

<?php if ($emergency !== ''): ?>
<div class="bg-medical-950 px-4 py-2 text-xs font-medium text-white">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
        <span class="flex items-center gap-2">
            <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
            <span><?= $view->t('hero.stat_emergency') ?></span>
        </span>
        <a class="font-bold text-teal-300 hover:underline" href="tel:<?= $view->e($emergency) ?>">
            <?= $view->e($emergency) ?>
        </a>
    </div>
</div>
<?php endif; ?>

<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl" data-header>
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">

        <a href="<?= $view->url('') ?>" class="flex shrink-0 items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-medical-700 text-xl font-extrabold text-white shadow-brand">A</span>
            <span class="leading-none">
                <strong class="block text-base font-extrabold text-medical-900 sm:text-lg"><?= $view->e($clinicName) ?></strong>
                <small class="text-[10px] font-semibold tracking-[0.18em] text-slate-500">ADDIS ABABA</small>
            </span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-700 lg:flex" aria-label="Main">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $view->url(ltrim($item['path'], '/')) ?>"
                   class="<?= $isActive($item['path']) ? 'text-medical-700' : 'hover:text-medical-600' ?> transition"
                   <?= $isActive($item['path']) ? 'aria-current="page"' : '' ?>>
                    <?= $view->t($item['key']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
            <?php /* Language switch is a link, not JS: it must work without
                      scripts and be crawlable for hreflang to mean anything. */ ?>
            <div class="flex rounded-xl border border-slate-200 bg-slate-100 p-1" role="group" aria-label="<?= $view->t('nav.language') ?>">
                <?php foreach (Locale::all() as $option): ?>
                    <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                       class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition <?= $option === $locale
                           ? 'bg-white text-medical-900 shadow-sm'
                           : 'text-slate-600 hover:text-slate-900' ?>"
                       <?= $option === $locale ? 'aria-current="true"' : '' ?>
                       lang="<?= $view->e($option->htmlLang()) ?>">
                        <?= $view->e($option->shortLabel()) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <a href="<?= $view->url('book') ?>" class="btn-primary btn-sm whitespace-nowrap">
                <?= $view->t('nav.book') ?>
            </a>
        </div>

        <button type="button"
                class="rounded-xl border border-slate-200 p-2.5 text-slate-700 lg:hidden"
                data-menu-toggle
                aria-expanded="false"
                aria-controls="mobile-menu"
                aria-label="<?= $view->t('nav.menu') ?>">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div id="mobile-menu" class="hidden border-t border-slate-200 bg-white px-4 py-4 lg:hidden" data-menu>
        <nav class="grid gap-1 text-sm font-semibold text-slate-700" aria-label="Mobile">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $view->url(ltrim($item['path'], '/')) ?>"
                   class="rounded-xl px-3 py-2.5 transition hover:bg-slate-50 <?= $isActive($item['path']) ? 'bg-medical-50 text-medical-700' : '' ?>">
                    <?= $view->t($item['key']) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= $view->url('my-booking') ?>" class="rounded-xl px-3 py-2.5 transition hover:bg-slate-50">
                <?= $view->t('nav.my_booking') ?>
            </a>
        </nav>

        <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
            <span class="text-xs font-bold text-slate-500"><?= $view->t('nav.language') ?>:</span>
            <?php foreach (Locale::all() as $option): ?>
                <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                   class="rounded-lg px-3 py-1.5 text-xs font-bold transition <?= $option === $locale
                       ? 'bg-medical-700 text-white'
                       : 'bg-slate-100 text-slate-700' ?>"
                   lang="<?= $view->e($option->htmlLang()) ?>">
                    <?= $view->e($option->nativeLabel()) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="<?= $view->url('book') ?>" class="btn-primary mt-4 w-full">
            <?= $view->t('nav.book') ?>
        </a>
    </div>
</header>
