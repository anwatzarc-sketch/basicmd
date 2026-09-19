<?php
/**
 * Location hub with an embedded map.
 *
 * Google Maps is used when an API key is configured; otherwise it falls back
 * to an OpenStreetMap iframe, which needs no key and no billing account. A
 * clinic without a Maps contract still gets a working map.
 *
 * @var \Aster\Presentation\View\View $view
 * @var array{lat:string,lng:string,zoom:int} $map
 * @var string $mapKey
 */

declare(strict_types=1);

$lat  = $map['lat'];
$lng  = $map['lng'];
$zoom = $map['zoom'];

$address = $settings->localized('address', $locale);
$hours   = $settings->localized('operating_hours', $locale);

$directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($lat . ',' . $lng);

$embedUrl = $mapKey !== ''
    ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapKey)
        . '&q=' . rawurlencode($lat . ',' . $lng) . '&zoom=' . $zoom
    : 'https://www.openstreetmap.org/export/embed.html?bbox='
        . ((float) $lng - 0.006) . '%2C' . ((float) $lat - 0.004) . '%2C'
        . ((float) $lng + 0.006) . '%2C' . ((float) $lat + 0.004)
        . '&layer=mapnik&marker=' . $lat . '%2C' . $lng;
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <div class="grid gap-10 lg:grid-cols-2">
        <div>
            <span class="eyebrow"><?= $view->t('locations.eyebrow') ?></span>
            <h1 class="section-title"><?= $view->t('locations.title', ['city' => $view->brand->mainCity]) ?></h1>

            <dl class="mt-8 grid gap-5">
                <?php
                $rows = [
                    ['locations.address', $address, null],
                    ['locations.phone',   $settings->string('phone_primary', ''), 'tel'],
                    ['locations.email',   $settings->string('email_public', ''), 'mailto'],
                    ['locations.hours',   $hours, null],
                ];
                foreach ($rows as [$labelKey, $value, $scheme]):
                    if ($value === '') continue;
                ?>
                    <div class="flex gap-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-medical-50 text-medical-700 dark:bg-medical-950/40 dark:text-medical-300" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </span>
                        <div>
                            <dt class="dt-label"><?= $view->t($labelKey) ?></dt>
                            <dd class="mt-1 font-semibold text-slate-900 dark:text-slate-100">
                                <?php if ($scheme !== null): ?>
                                    <a class="hover:text-medical-700 dark:hover:text-medical-300" href="<?= $view->e($scheme . ':' . $value) ?>"><?= $view->e($value) ?></a>
                                <?php else: ?>
                                    <?= $view->e($value) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="<?= $view->e($directionsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-primary">
                    <?= $view->t('locations.directions') ?>
                </a>
                <a href="<?= $view->url('book') ?>" class="btn-secondary"><?= $view->t('nav.book') ?></a>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="<?= $view->url('services') ?>" class="btn-ghost btn-sm"><?= $view->t('nav.services') ?></a>
                <a href="<?= $view->url('doctors') ?>" class="btn-ghost btn-sm"><?= $view->t('nav.doctors') ?></a>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 shadow-card dark:border-slate-800">
            <iframe
                title="<?= $view->e($view->tRaw('locations.title', ['city' => $view->brand->mainCity])) ?>"
                src="<?= $view->e($embedUrl) ?>"
                class="h-[420px] w-full border-0"
                width="600" height="420"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen></iframe>
        </div>
    </div>
</section>
