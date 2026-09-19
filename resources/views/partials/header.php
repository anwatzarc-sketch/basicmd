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
$clinicName = $settings?->localized('clinic_name', $locale, $view->brand->businessName)
    ?: $view->brand->businessName;

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
        <?php /* A light brand tint, not the footer accent: this strip is always
                 the dark brand shade, whereas the footer's colours are
                 administrator-controlled and may be light. */ ?>
        <a class="font-bold text-medical-300 hover:underline" href="tel:<?= $view->e($emergency) ?>">
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
            <?php /*
              The strapline is deliberately not in this row.

              The row is capped at max-w-7xl, so its width budget is fixed at
              about 1216px no matter how wide the screen is - a breakpoint
              cannot buy more room. Six nav items plus three controls fit that
              budget in English and Amharic but not in Afaan Oromo, whose
              labels run roughly 40% longer, and the strapline was both the
              least informative element in the row and the one that grows most
              with translation. Dropping it keeps a single-line header in all
              three languages instead of one that is taller in one of them.

              It still renders in the footer, and the same copy opens the hero.
            */ ?>
            <span class="leading-none">
                <strong class="block text-base font-extrabold text-medical-900 dark:text-medical-200 sm:text-lg"><?= $view->e($clinicName) ?></strong>
            </span>
        </a>

        <?php /* whitespace-nowrap is load-bearing: "Health Packages" and the
                 longer Amharic and Afaan Oromo labels otherwise break across
                 two lines and the header grows a second row. */ ?>
        <nav class="hidden items-center gap-4 text-[0.8125rem] font-semibold text-slate-700 dark:text-slate-200 xl:flex" aria-label="Main">
            <?php foreach ($nav as $item): ?>
                <a href="<?= $view->url(ltrim($item['path'], '/')) ?>"
                   class="whitespace-nowrap <?= $isActive($item['path']) ? 'text-medical-700 dark:text-medical-300' : 'hover:text-medical-600 dark:hover:text-medical-300' ?> transition"
                   <?= $isActive($item['path']) ? 'aria-current="page"' : '' ?>>
                    <?= $view->t($item['key']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden items-center gap-2 xl:flex">
            <?php /* Language switch stays a set of real links, opened by a
                     <details>: it must work without scripts and be crawlable
                     for hreflang to mean anything. */ ?>
            <details class="lang-menu relative">
                <summary class="control-pill cursor-pointer" aria-label="<?= $view->t('nav.language') ?>">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/>
                    </svg>
                    <?php /* The short code, not the native name: the row has a fixed
                             width budget and "Afaan Oromoo" is four times the
                             width of "OM". The menu below spells each one out. */ ?>
                    <span lang="<?= $view->e($locale->htmlLang()) ?>"><?= $view->e($locale->shortLabel()) ?></span>
                    <svg class="h-3.5 w-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="lang-menu__panel">
                    <?php foreach (Locale::all() as $option): ?>
                        <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                           class="lang-menu__item"
                           <?= $option === $locale ? 'aria-current="true"' : '' ?>
                           lang="<?= $view->e($option->htmlLang()) ?>">
                            <span><?= $view->e($option->nativeLabel()) ?></span>
                            <span class="text-xs opacity-60"><?= $view->e($option->shortLabel()) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>

            <?php /* Three states, not two: "system" is a real preference and
                     the page already honours it (an absent stored value means
                     follow the OS). The pressed state is set by app.js, since
                     the choice lives in localStorage and the server cannot
                     know it at render time. */ ?>
            <div class="control-group" role="group" aria-label="<?= $view->t('nav.theme') ?>">
                <button type="button" class="control-icon" data-theme-set="system" aria-pressed="false"
                        title="<?= $view->e($view->tRaw('nav.theme_system')) ?>">
                    <span class="sr-only"><?= $view->t('nav.theme_system') ?></span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2"/>
                        <path stroke-linecap="round" d="M8 20h8m-4-4v4"/>
                    </svg>
                </button>
                <button type="button" class="control-icon" data-theme-set="light" aria-pressed="false"
                        title="<?= $view->e($view->tRaw('nav.theme_light')) ?>">
                    <span class="sr-only"><?= $view->t('nav.theme_light') ?></span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M18.364 18.364l-1.414-1.414M7.05 7.05L5.636 5.636M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                    </svg>
                </button>
                <button type="button" class="control-icon" data-theme-set="dark" aria-pressed="false"
                        title="<?= $view->e($view->tRaw('nav.theme_dark')) ?>">
                    <span class="sr-only"><?= $view->t('nav.theme_dark') ?></span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>

            <a href="<?= $view->url('book') ?>" class="btn-primary btn-sm whitespace-nowrap rounded-full">
                <?= $view->t('nav.book') ?>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>

        <button type="button"
                class="rounded-xl border border-slate-200 dark:border-slate-700 p-2.5 text-slate-700 dark:text-slate-200 xl:hidden"
                data-menu-toggle
                aria-expanded="false"
                aria-controls="mobile-menu"
                aria-label="<?= $view->t('nav.menu') ?>">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div id="mobile-menu" class="hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-4 xl:hidden" data-menu>
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

        <div class="mt-3 grid gap-3 border-t border-slate-100 dark:border-slate-800 pt-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="dt-label"><?= $view->t('nav.language') ?></span>
                <?php foreach (Locale::all() as $option): ?>
                    <a href="<?= $view->e($withLang(parse_url($current, PHP_URL_PATH) ?: '/', $option->value)) ?>"
                       class="rounded-full px-3 py-1.5 text-xs font-bold transition <?= $option === $locale
                           ? 'bg-medical-700 text-white'
                           : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' ?>"
                       <?= $option === $locale ? 'aria-current="true"' : '' ?>
                       lang="<?= $view->e($option->htmlLang()) ?>">
                        <?= $view->e($option->nativeLabel()) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="dt-label"><?= $view->t('nav.theme') ?></span>
                <div class="control-group" role="group" aria-label="<?= $view->t('nav.theme') ?>">
                    <button type="button" class="control-icon" data-theme-set="system" aria-pressed="false">
                        <span class="sr-only"><?= $view->t('nav.theme_system') ?></span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="12" rx="2"/>
                            <path stroke-linecap="round" d="M8 20h8m-4-4v4"/>
                        </svg>
                    </button>
                    <button type="button" class="control-icon" data-theme-set="light" aria-pressed="false">
                        <span class="sr-only"><?= $view->t('nav.theme_light') ?></span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M18.364 18.364l-1.414-1.414M7.05 7.05L5.636 5.636M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                        </svg>
                    </button>
                    <button type="button" class="control-icon" data-theme-set="dark" aria-pressed="false">
                        <span class="sr-only"><?= $view->t('nav.theme_dark') ?></span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <a href="<?= $view->url('book') ?>" class="btn-primary mt-4 w-full rounded-full">
            <?= $view->t('nav.book') ?>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </div>
</header>

<?php /* nonce required: the CSP blocks any inline script without one. */ ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>">
(function () {
    var KEY   = 'aster-theme';
    var LIGHT = 'rgb(<?= $view->e($view->brand->primary('700')) ?>)';
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    // "system" is the absence of a stored value, which is exactly what the
    // bootstrap script in <head> already reads. Storing nothing is therefore
    // the third state rather than a special case.
    var stored = function () {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    };

    var apply = function (choice) {
        var dark = choice === 'dark' || (choice === null && media.matches);

        document.documentElement.classList.toggle('dark', dark);

        // Keep the mobile browser chrome in step with the page. The light
        // value is the configured brand primary, not a literal - otherwise
        // switching theme repaints the chrome in the default teal.
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', dark ? '#020617' : LIGHT);
        }

        document.querySelectorAll('[data-theme-set]').forEach(function (btn) {
            var mine = btn.getAttribute('data-theme-set');
            var on   = choice === null ? mine === 'system' : mine === choice;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    };

    document.querySelectorAll('[data-theme-set]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var choice = btn.getAttribute('data-theme-set');

            try {
                if (choice === 'system') {
                    localStorage.removeItem(KEY);
                } else {
                    localStorage.setItem(KEY, choice);
                }
            } catch (e) {
                // Private mode: the choice simply will not persist.
            }

            apply(choice === 'system' ? null : choice);
        });
    });

    // Follow the OS live, but only while the visitor is actually on "system".
    media.addEventListener('change', function () {
        if (stored() === null) { apply(null); }
    });

    // The stored preference is only readable on the client, so the pressed
    // state cannot be rendered server-side.
    apply(stored());
})();
</script>