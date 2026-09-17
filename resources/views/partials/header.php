<?php
/**
 * Public site header: brand, navigation, language switcher, mobile menu, day/night theme toggler.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale     $locale
 */

declare(strict_types=1);

use Aster\Domain\Enum\Locale;

$settings   = $settings ?? null;
$clinicName = $view->brand->businessName;

if ($locale === Locale::AM && $settings !== null) {
    $clinicName = $settings->string('clinic_name_am', $clinicName);
}

/**
 * Strapline under the clinic name in the logo lockup.
 *
 * Driven by Settings -> Clinic identity (`tagline` / `tagline_am`) rather than
 * being hardcoded, so the clinic can change it without a deploy. Amharic falls
 * back to the English value when it has not been translated yet, and the whole
 * element is dropped when both are blank - an empty line under the name looks
 * like a rendering fault.
 */
$tagline = $locale === Locale::AM
    ? ($settings?->string('tagline_am', '') ?: ($settings?->string('tagline', '') ?? ''))
    : ($settings?->string('tagline', '') ?? '');

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

<header class="sticky top-0 z-40 border-b border-slate-200/80 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl" data-header>
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">

        <a href="<?= $view->url('') ?>" class="flex shrink-0 items-center gap-3">
            <?php if ($view->brand->logoImage !== null): ?>
                <img src="<?= $view->media($view->brand->logoImage) ?>" alt=""
                     class="h-11 w-11 rounded-2xl object-cover shadow-brand">
            <?php else: ?>
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-medical-700 text-xl font-extrabold text-white shadow-brand"><?= $view->e($view->brand->businessInitials) ?></span>
            <?php endif; ?>
            <span class="leading-none">
                <strong class="block text-base font-extrabold text-medical-900 dark:text-medical-200 sm:text-lg"><?= $view->e($clinicName) ?></strong>
                <?php if ($tagline !== ''): ?>
                    <?php /* Truncated with a max-width: an over-long tagline would
                             otherwise push the navigation off the header on a phone.
                             title= keeps the full text reachable on hover. */ ?>
                    <small class="mt-0.5 block max-w-[10rem] truncate text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-500 dark:text-slate-400 sm:max-w-[15rem]"
                           title="<?= $view->e($tagline) ?>"><?= $view->e($tagline) ?></small>
                <?php endif; ?>
            </span>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-700 dark:text-slate-200 lg:flex" aria-label="Main">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $view->url(ltrim($item['path'], '/')) ?>"
                   class="<?= $isActive($item['path']) ? 'text-medical-700 dark:text-medical-400' : 'hover:text-medical-600 dark:hover:text-medical-300' ?> transition"
                   <?= $isActive($item['path']) ? 'aria-current="page"' : '' ?>>
                    <?= $view->t($item['key']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
            <?php /* Day/Night Theme Toggler */ ?>
            <button type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-100/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition hover:bg-slate-200/80 dark:hover:bg-slate-700/80"
                    data-theme-toggle
                    aria-label="Toggle day or night theme">
                <!-- Sun Icon (visible in dark mode) -->
                <svg class="hidden h-4 w-4 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M18.364 18.364l-1.414-1.414M7.05 7.05L5.636 5.636M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
                <!-- Moon Icon (visible in light mode) -->
                <svg class="block h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>

            <?php /* Language switch is a link, not JS: it must work without
                     scripts and be crawlable for hreflang to mean anything. */ ?>
            <div class="flex rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 p-1" role="group" aria-label="<?= $view->t('nav.language') ?>">
                <?php foreach (Locale::all() as $option): ?>
                    <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                       class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition <?= $option === $locale
                           ? 'bg-white dark:bg-slate-700 text-medical-900 dark:text-white shadow-sm'
                           : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>"
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
                class="rounded-xl border border-slate-200 dark:border-slate-700 p-2.5 text-slate-700 dark:text-slate-200 lg:hidden"
                data-menu-toggle
                aria-expanded="false"
                aria-controls="mobile-menu"
                aria-label="<?= $view->t('nav.menu') ?>">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div id="mobile-menu" class="hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-4 lg:hidden" data-menu>
        <nav class="grid gap-1 text-sm font-semibold text-slate-700 dark:text-slate-200" aria-label="Mobile">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $view->url(ltrim($item['path'], '/')) ?>"
                   class="rounded-xl px-3 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800 <?= $isActive($item['path']) ? 'bg-medical-50 dark:bg-medical-950 text-medical-700 dark:text-medical-300' : '' ?>">
                    <?= $view->t($item['key']) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= $view->url('my-booking') ?>" class="rounded-xl px-3 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800">
                <?= $view->t('nav.my_booking') ?>
            </a>
        </nav>

        <div class="mt-3 flex items-center justify-between border-t border-slate-100 dark:border-slate-800 pt-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400"><?= $view->t('nav.language') ?>:</span>
                <?php foreach (Locale::all() as $option): ?>
                    <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                       class="rounded-lg px-3 py-1.5 text-xs font-bold transition <?= $option === $locale
                           ? 'bg-medical-700 text-white'
                           : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' ?>"
                       lang="<?= $view->e($option->htmlLang()) ?>">
                        <?= $view->e($option->nativeLabel()) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Mobile Theme Toggle -->
            <button type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-100/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition hover:bg-slate-200/80 dark:hover:bg-slate-700/80"
                    data-theme-toggle
                    aria-label="Toggle day or night theme">
                <svg class="hidden h-4 w-4 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M18.364 18.364l-1.414-1.414M7.05 7.05L5.636 5.636M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                </svg>
                <svg class="block h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
        </div>

        <a href="<?= $view->url('book') ?>" class="btn-primary mt-4 w-full">
            <?= $view->t('nav.book') ?>
        </a>
    </div>
</header>

<?php /* nonce required: the CSP blocks any inline script without one. */ ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>">
(function () {
    var toggleBtns = document.querySelectorAll('[data-theme-toggle]');

    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var isDark = document.documentElement.classList.toggle('dark');

            try {
                localStorage.setItem('aster-theme', isDark ? 'dark' : 'light');
            } catch (e) {
                // Private mode: the choice simply will not persist.
            }

            // Keep the mobile browser chrome in step with the page.
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', isDark ? '#020617' : '#056460');
            }
        });
    });
})();
</script>