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
$clinicName = $settings?->localized('clinic_name', $locale, $view->brand->businessName)
    ?: $view->brand->businessName;

$address = $settings?->localized('address', $locale) ?? '';
$hours   = $settings?->localized('operating_hours', $locale) ?? '';

$phone = $settings?->string('phone_primary', '') ?? '';
$email = $settings?->string('email_public', '') ?? '';

$social = array_filter([
    'Facebook'  => $settings?->get('facebook_url'),
    'Telegram'  => $settings?->get('telegram_url'),
    'Instagram' => $settings?->get('instagram_url'),
    'LinkedIn'  => $settings?->get('linkedin_url'),
]);
?>
<footer class="brand-footer">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">

        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <?php if ($view->brand->logoImage !== null): ?>
                    <img src="<?= $view->media($view->brand->logoImage) ?>" alt=""
                         class="h-11 w-11 rounded-2xl object-cover">
                <?php else: ?>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-medical-700 text-xl font-extrabold text-white"><?= $view->e($view->brand->businessInitials) ?></span>
                <?php endif; ?>
                <b class="text-xl"><?= $view->e($clinicName) ?></b>
            </div>
            <p class="mt-4 max-w-md text-sm leading-7 opacity-80">
                <?= $view->t('footer.about', ['city' => $view->brand->mainCity]) ?>
            </p>

            <?php if ($social !== []): ?>
                <div class="mt-5 flex flex-wrap gap-2">
                    <?php foreach ($social as $name => $url): ?>
                        <a href="<?= $view->href((string) $url) ?>" rel="noopener noreferrer" target="_blank"
                           class="footer-chip rounded-xl border px-3 py-1.5 text-xs font-bold opacity-75 transition hover:opacity-100">
                            <?= $view->e($name) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <b class="footer-heading"><?= $view->t('footer.explore') ?></b>
            <nav class="mt-4 grid gap-3 text-sm opacity-75" aria-label="Footer">
                <a class="transition hover:opacity-100" href="<?= $view->url('services') ?>"><?= $view->t('nav.services') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->url('doctors') ?>"><?= $view->t('nav.doctors') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->url('packages') ?>"><?= $view->t('nav.packages') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->url('health') ?>"><?= $view->t('nav.articles') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->url('my-booking') ?>"><?= $view->t('nav.my_booking') ?></a>
            </nav>
        </div>

        <div>
            <b class="footer-heading"><?= $view->t('footer.contact') ?></b>
            <address class="mt-4 grid gap-3 text-sm not-italic opacity-75">
                <?php if ($address !== ''): ?>
                    <span><?= $view->e($address) ?></span>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <a class="transition hover:opacity-100" href="tel:<?= $view->e($phone) ?>"><?= $view->e($phone) ?></a>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <a class="transition hover:opacity-100" href="mailto:<?= $view->e($email) ?>"><?= $view->e($email) ?></a>
                <?php endif; ?>
                <?php if ($hours !== ''): ?>
                    <span><?= $view->e($hours) ?></span>
                <?php endif; ?>
            </address>
        </div>
    </div>

    <div class="footer-rule border-t">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-5 text-xs opacity-75 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <span>&copy; <?= date('Y') ?> <?= $view->e($clinicName) ?>. <?= $view->t('footer.rights') ?></span>
            <nav class="flex flex-wrap gap-4" aria-label="<?= $view->t('footer.legal') ?>">
                <a class="transition hover:opacity-100" href="<?= $view->url('privacy') ?>"><?= $view->t('footer.privacy') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->url('terms') ?>"><?= $view->t('footer.terms') ?></a>
                <a class="transition hover:opacity-100" href="<?= $view->adminUrl('login') ?>" rel="nofollow"><?= $view->t('footer.staff') ?></a>
            </nav>
        </div>
    </div>
</footer>
