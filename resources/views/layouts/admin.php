<?php
/**
 * Admin portal layout.
 *
 * The sidebar is built from the signed-in user's permissions, so a
 * receptionist never sees a link to a page that would 403 them. That is a
 * usability decision as much as a security one - the permission check in the
 * middleware is what actually enforces access.
 *
 * @var \Aster\Presentation\View\View  $view
 * @var \Aster\Domain\Entity\User|null $user
 * @var string                         $content
 * @var array                          $meta
 * @var string                         $cspNonce
 */

declare(strict_types=1);

$meta    = $meta ?? [];
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base    = '/' . $view->config->adminPath;

/**
 * Sidebar definition: [path, label, permission, badge count].
 * A null permission means every signed-in user sees it.
 */
$nav = [
    ['',             'Dashboard',        null,                 null, 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['appointments', 'Appointments',     'appointments.view',  $pendingAppointments ?? null, 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['payments',     'Payments',         'payments.view',      $pendingPayments ?? null, 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ['doctors',      'Doctors',          'doctors.view',       null, 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['services',     'Services',         'services.view',      null, 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
    ['packages',     'Health Packages',  'packages.view',      null, 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
    ['facilities',   'Facilities',       'facilities.view',    null, 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    ['articles',     'Health Articles',  'articles.view',      null, 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z'],
    ['inquiries',    'Enquiries',        'inquiries.view',     $unreadInquiries ?? null, 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ['users',        'Staff Accounts',   'users.view',         null, 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['settings',     'Settings',         'settings.view',      null, 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
];

$isActive = static function (string $path) use ($current, $base): bool {
    $full = $path === '' ? $base : $base . '/' . $path;

    return $path === ''
        ? rtrim($current, '/') === rtrim($base, '/') || str_ends_with($current, '/dashboard')
        : str_starts_with($current, $full);
};
?>
<!doctype html>
<html lang="<?= $view->e($locale->htmlLang()) ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#032423">
    <title><?= $view->e(($meta['title'] ?? 'Admin') . ' | Aster Admin') ?></title>
    <meta name="csrf-token" content="<?= $view->e($view->csrfToken()) ?>">
    <link rel="icon" href="<?= $view->asset('assets/img/favicon.svg') ?>" type="image/svg+xml">

    <?php if ($view->config->assetsBuilt()): ?>
        <link rel="stylesheet" href="<?= $view->asset('dist/css/app.min.css') ?>">
    <?php else: ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script nonce="<?= $view->e($cspNonce) ?>">
            tailwind.config = { theme: { extend: { colors: { medical: {
                50:'#effcfb',100:'#d8f7f4',200:'#b3ede8',300:'#82ded7',400:'#4bc4bd',
                500:'#0f8f89',600:'#087b77',700:'#056460',800:'#0c4a47',900:'#063b3a',950:'#032423'
            }, gold:'#d99a32' } } } };
        </script>
    <?php endif; ?>
</head>
<body class="h-full bg-slate-100 font-sans antialiased">

<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="flex min-h-full flex-col lg:flex-row">

    <!-- ----------------------------- Sidebar ----------------------------- -->
    <aside class="flex w-full shrink-0 flex-col justify-between bg-medical-950 text-white lg:w-64" data-admin-sidebar>
        <div>
            <div class="flex items-center justify-between px-5 py-5">
                <a href="<?= $view->adminUrl('') ?>" class="flex items-center gap-3">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-medical-700 text-base font-extrabold">A</span>
                    <span class="leading-tight">
                        <strong class="block text-sm font-extrabold">Aster Medical</strong>
                        <small class="text-[10px] font-bold uppercase tracking-[0.16em] text-teal-300">Admin</small>
                    </span>
                </a>

                <button type="button" class="rounded-lg border border-white/15 p-2 lg:hidden"
                        data-menu-toggle aria-controls="admin-nav" aria-expanded="false" aria-label="Menu">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

            <nav id="admin-nav" class="hidden space-y-1 px-3 pb-4 lg:block" data-menu aria-label="Admin">
                <?php foreach ($nav as [$path, $label, $permission, $badge, $icon]): ?>
                    <?php if ($permission !== null && ($user === null || !$user->can($permission))) { continue; } ?>
                    <a href="<?= $view->adminUrl($path) ?>"
                       class="admin-nav-link <?= $isActive($path) ? 'admin-nav-active' : '' ?>"
                       <?= $isActive($path) ? 'aria-current="page"' : '' ?>>
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
                            </svg>
                            <span><?= $view->e($label) ?></span>
                        </span>
                        <?php if (!empty($badge)): ?>
                            <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-extrabold text-slate-950">
                                <?= (int) $badge ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if ($user !== null): ?>
            <div class="hidden border-t border-white/10 bg-black/20 p-4 lg:block">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-teal-500 text-sm font-bold text-medical-950">
                        <?= $view->e($user->initials()) ?>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-white"><?= $view->e($user->fullName) ?></p>
                        <p class="truncate text-[10px] font-medium text-teal-300"><?= $view->e($user->role->label()) ?></p>
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <a href="<?= $view->adminUrl('password') ?>"
                       class="flex-1 rounded-lg border border-white/15 px-2 py-1.5 text-center text-[11px] font-bold text-white/70 transition hover:text-white">
                        Password
                    </a>
                    <form method="post" action="<?= $view->adminUrl('logout') ?>" class="flex-1">
                        <?= $view->csrfField() ?>
                        <button type="submit"
                                class="w-full rounded-lg border border-white/15 px-2 py-1.5 text-[11px] font-bold text-white/70 transition hover:text-white">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <!-- ----------------------------- Main ----------------------------- -->
    <main id="admin-main" class="min-w-0 flex-1">
        <header class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-medical-600">Administration</p>
                    <h1 class="mt-1 text-2xl font-extrabold text-medical-900"><?= $view->e($meta['title'] ?? 'Dashboard') ?></h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= $view->url('') ?>" target="_blank" rel="noopener"
                       class="btn-secondary btn-sm">View site</a>
                    <?php if ($user !== null && $user->can('appointments.write')): ?>
                        <a href="<?= $view->adminUrl('appointments/create') ?>" class="btn-primary btn-sm">
                            New appointment
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="p-4 sm:p-6 lg:p-8">
            <?= $view->partial('partials/flash', ['view' => $view, 'flash' => $flash ?? []]) ?>
            <?= $content ?>
        </div>
    </main>
</div>

<?= $view->partial('partials/confirm-dialog', ['view' => $view]) ?>

<script nonce="<?= $view->e($cspNonce) ?>" src="<?= $view->asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
