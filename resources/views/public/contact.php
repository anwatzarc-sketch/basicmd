<?php
/**
 * Contact page.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var array<string,string> $old
 * @var array<string,string> $errors
 */

declare(strict_types=1);

$address = $settings->localized('address', $locale);
$hours   = $settings->localized('operating_hours', $locale);

// Hoisted out of the contact <dl> below so the emergency line can be given its
// own high-contrast block: it is the one number a visitor in a hurry needs.
$emergency = $settings->string('phone_emergency', '');
?>
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
    <div class="grid gap-10 lg:grid-cols-2">

        <div>
            <span class="eyebrow"><?= $view->t('contact.eyebrow') ?></span>
            <h1 class="section-title"><?= $view->t('contact.title') ?></h1>
            <p class="section-lead"><?= $view->t('contact.lead') ?></p>

            <?php if ($emergency !== ''): ?>
                <?php /* Amber is a semantic urgency colour here, not a brand tint,
                          so it is deliberately left off the medical-* scale. */ ?>
                <div class="alert-warning mt-8">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <div class="flex-1">
                        <h2 class="text-xs font-bold uppercase tracking-wider opacity-80"><?= $view->t('hero.stat_emergency') ?></h2>
                        <a class="mt-1 block text-2xl font-extrabold hover:underline"
                           href="<?= $view->e('tel:' . $emergency) ?>"><?= $view->e($emergency) ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <dl class="mt-8 grid gap-5">
                <?php foreach ([
                    ['locations.address', $address, null],
                    ['locations.phone',   $settings->string('phone_primary', ''), 'tel'],
                    ['locations.email',   $settings->string('email_public', ''), 'mailto'],
                    ['locations.hours',   $hours, null],
                ] as [$labelKey, $value, $scheme]): ?>
                    <?php if ($value === '') { continue; } ?>
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
                <?php endforeach; ?>
            </dl>
        </div>

        <form method="post" action="<?= $view->url('contact') ?>" class="card p-6 sm:p-8">
            <?= $view->csrfField() ?>

            <?php /* Honeypot. Hidden from sighted users and from screen readers
                      via aria-hidden + tabindex, so only a bot fills it in. */ ?>
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="name">
                        <?= $view->t('booking.name') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <input class="input <?= isset($errors['con_name']) ? 'input-error' : '' ?>"
                           type="text" id="name" name="con_name" autocomplete="name" required
                           value="<?= $view->e($old['con_name'] ?? '') ?>">
                    <?php if (isset($errors['con_name'])): ?>
                        <span class="field-error"><?= $view->e($errors['con_name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="phone">
                        <?= $view->t('booking.phone') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <input class="input <?= isset($errors['phone']) ? 'input-error' : '' ?>"
                           type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel" required
                           placeholder="<?= $view->e($view->tRaw('booking.phone_ph')) ?>"
                           value="<?= $view->e($old['phone'] ?? '') ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="field-error"><?= $view->e($errors['phone']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field sm:col-span-2">
                    <label class="label" for="email"><?= $view->t('booking.email') ?></label>
                    <input class="input <?= isset($errors['email']) ? 'input-error' : '' ?>"
                           type="email" id="email" name="email" autocomplete="email"
                           placeholder="<?= $view->e($view->tRaw('booking.email_ph')) ?>"
                           value="<?= $view->e($old['email'] ?? '') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error"><?= $view->e($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field sm:col-span-2">
                    <label class="label" for="subject"><?= $view->t('email.label_reason') ?></label>
                    <input class="input" type="text" id="subject" name="subject"
                           value="<?= $view->e($old['subject'] ?? '') ?>">
                </div>

                <div class="field sm:col-span-2">
                    <label class="label" for="message">
                        <?= $view->t('booking.notes') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <textarea class="textarea <?= isset($errors['message']) ? 'input-error' : '' ?>"
                              id="message" name="message" rows="5" required><?= $view->e($old['message'] ?? '') ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <span class="field-error"><?= $view->e($errors['message']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn-primary mt-6 w-full"><?= $view->t('form.submit') ?></button>
        </form>
    </div>
</section>
