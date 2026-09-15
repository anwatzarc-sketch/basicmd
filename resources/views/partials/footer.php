<?php
/**
 * Public site footer.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Enum\Locale     $locale
 */

declare(strict_types=1);

use Aster\Domain\Enum\Locale;

$settings   = $settings ?? null;
$clinicName = $settings?->string('clinic_name', 'Aster Medical Center') ?? 'Aster Medical Center';

if ($locale === Locale::AM && $settings !== null) {
    $clinicName = $settings->string('clinic_name_am', $clinicName);
}

$address = $locale === Locale::AM
    ? ($settings?->string('address_am', '') ?: $settings?->string('address', '') ?? '')
    : ($settings?->string('address', '') ?? '');

$hours = $locale === Locale::AM
    ? ($settings?->string('operating_hours_am', '') ?: $settings?->string('operating_hours', '') ?? '')
    : ($settings?->string('operating_hours', '') ?? '');

$phone = $settings?->string('phone_primary', '') ?? '';
$email = $settings?->string('email_public', '') ?? '';

$social = array_filter([
    'Facebook'  => $settings?->get('facebook_url'),
    'Telegram'  => $settings?->get('telegram_url'),
    'Instagram' => $settings?->get('instagram_url'),
    'LinkedIn'  => $settings?->get('linkedin_url'),
]);
?>
<footer class="bg-medical-950 text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">

        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-medical-700 text-xl font-extrabold text-white">A</span>
                <b class="text-xl tracking-tight"><?= $view->e($clinicName) ?></b>
            </div>
            <p class="mt-4 max-w-md text-sm leading-7 text-white/60">
                <?= $view->t('footer.about') ?>
            </p>

            <?php if ($social !== []): ?>
                <div class="mt-5 flex flex-wrap gap-2">
                    <?php foreach ($social as $name => $url): ?>
                        <a href="<?= $view->href((string) $url) ?>" rel="noopener noreferrer" target="_blank"
                           class="rounded-xl border border-white/15 px-3 py-1.5 text-xs font-bold text-white/70 transition hover:border-teal-300 hover:text-teal-300">
                            <?= $view->e($name) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <b class="text-sm font-bold uppercase tracking-wider text-teal-300"><?= $view->t('footer.explore') ?></b>
            <nav class="mt-4 grid gap-3 text-sm text-white/70" aria-label="Footer">
                <a class="transition hover:text-white" href="<?= $view->url('services') ?>"><?= $view->t('nav.services') ?></a>
                <a class="transition hover:text-white" href="<?= $view->url('doctors') ?>"><?= $view->t('nav.doctors') ?></a>
                <a class="transition hover:text-white" href="<?= $view->url('packages') ?>"><?= $view->t('nav.packages') ?></a>
                <a class="transition hover:text-white" href="<?= $view->url('health') ?>"><?= $view->t('nav.articles') ?></a>
                <a class="transition hover:text-white" href="<?= $view->url('my-booking') ?>"><?= $view->t('nav.my_booking') ?></a>
            </nav>
        </div>

        <div>
            <b class="text-sm font-bold uppercase tracking-wider text-teal-300"><?= $view->t('footer.contact') ?></b>
            <address class="mt-4 grid gap-3 text-sm not-italic text-white/70">
                <?php if ($address !== ''): ?>
                    <span><?= $view->e($address) ?></span>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <a class="transition hover:text-white" href="tel:<?= $view->e($phone) ?>"><?= $view->e($phone) ?></a>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <a class="transition hover:text-white" href="mailto:<?= $view->e($email) ?>"><?= $view->e($email) ?></a>
                <?php endif; ?>
                <?php if ($hours !== ''): ?>
                    <span class="text-white/50"><?= $view->e($hours) ?></span>
                <?php endif; ?>
            </address>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-5 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <span>&copy; <?= date('Y') ?> <?= $view->e($clinicName) ?>. <?= $view->t('footer.rights') ?></span>
            <nav class="flex flex-wrap gap-4" aria-label="<?= $view->t('footer.legal') ?>">
                <a class="transition hover:text-white/70" href="<?= $view->url('privacy') ?>"><?= $view->t('footer.privacy') ?></a>
                <a class="transition hover:text-white/70" href="<?= $view->url('terms') ?>"><?= $view->t('footer.terms') ?></a>
                <a class="transition hover:text-white/70" href="<?= $view->adminUrl('login') ?>" rel="nofollow"><?= $view->t('footer.staff') ?></a>
            </nav>
        </div>
    </div>
</footer>
