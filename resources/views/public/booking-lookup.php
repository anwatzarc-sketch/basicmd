<?php
/**
 * "Check my booking" lookup.
 *
 * Reference AND phone are both required. The reference alone is unguessable,
 * but it travels in an email that can be forwarded, so it is not treated as
 * sufficient authorisation on its own.
 *
 * @var \Aster\Presentation\View\View $view
 */

declare(strict_types=1);
?>
<section class="mx-auto max-w-lg px-4 py-16 sm:px-6 lg:py-24">
    <div class="text-center">
        <span class="eyebrow"><?= $view->t('nav.my_booking') ?></span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-medical-900"><?= $view->t('lookup.title') ?></h1>
        <p class="mt-3 text-slate-600"><?= $view->t('lookup.lead') ?></p>
    </div>

    <form method="post" action="<?= $view->url('my-booking') ?>" class="card mt-8 grid gap-5 p-6 sm:p-8">
        <?= $view->csrfField() ?>

        <div class="field">
            <label class="label" for="reference"><?= $view->t('lookup.reference') ?></label>
            <input class="input font-mono uppercase tracking-wider" type="text" id="reference" name="reference"
                   placeholder="<?= $view->e($view->tRaw('lookup.ref_ph')) ?>"
                   autocapitalize="characters" autocomplete="off" required
                   value="<?= $view->e($old['reference'] ?? '') ?>">
        </div>

        <div class="field">
            <label class="label" for="phone"><?= $view->t('lookup.phone') ?></label>
            <input class="input" type="tel" id="phone" name="phone" inputmode="tel"
                   placeholder="<?= $view->e($view->tRaw('booking.phone_ph')) ?>" required
                   value="<?= $view->e($old['phone'] ?? '') ?>">
        </div>

        <button type="submit" class="btn-primary w-full"><?= $view->t('lookup.submit') ?></button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="<?= $view->url('book') ?>" class="font-semibold text-medical-700 hover:underline">
            <?= $view->t('nav.book') ?>
        </a>
    </p>
</section>
