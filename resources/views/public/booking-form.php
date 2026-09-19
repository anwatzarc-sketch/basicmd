<?php
/**
 * The booking form.
 *
 * Progressive enhancement is deliberate: the form is a plain POST that works
 * with JavaScript disabled. The fetch-driven availability grid and live price
 * summary are enhancements layered on top. A patient on a cheap Android phone
 * with a flaky connection can still book.
 *
 * @var \MediCareMini\Presentation\View\View             $view
 * @var \MediCareMini\Domain\Enum\Locale                 $locale
 * @var list<\MediCareMini\Domain\Entity\MedicalService> $services
 * @var list<\MediCareMini\Domain\Entity\HealthPackage>  $packages
 * @var list<\MediCareMini\Domain\Entity\Doctor>         $doctors
 * @var array                                     $preselected
 * @var array<string,string>                      $old
 * @var array<string,string>                      $errors
 */

declare(strict_types=1);

use MediCareMini\Domain\Enum\TimeSlot;

$t         = $view->translator;
$emergency = $settings->string('phone_emergency', $settings->string('phone_primary', ''));

/** Old input wins on a validation re-render, then the ?service= deep link. */
$value = static fn (string $key, mixed $fallback = ''): string
    => (string) ($old[$key] ?? $fallback ?? '');

$hasError = static fn (string $key): bool => isset($errors[$key]);
?>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

    <?= $view->partial('partials/booking-steps', ['view' => $view, 'current' => 1]) ?>

    <div class="grid gap-10 lg:grid-cols-[0.75fr_1.25fr]">

        <!-- ------------------------------ Sidebar ------------------------------ -->
        <aside>
            <span class="eyebrow"><?= $view->t('booking.eyebrow') ?></span>
            <h1 class="section-title"><?= $view->t('booking.title') ?></h1>
            <p class="section-lead"><?= $view->t('booking.lead') ?></p>

            <!-- Live price summary, updated by app.js as selections change. -->
            <div class="card-pad mt-8" data-quote-panel hidden>
                <h2 class="panel-title">
                    <?= $view->t('booking.summary') ?>
                </h2>
                <dl class="mt-4 grid gap-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600 dark:text-slate-300"><?= $view->t('booking.subtotal') ?></dt>
                        <dd class="font-bold text-slate-900 dark:text-slate-100" data-quote-base>&mdash;</dd>
                    </div>
                    <div class="flex items-center justify-between" data-quote-surcharge-row hidden>
                        <dt class="text-slate-600 dark:text-slate-300"><?= $view->t('booking.surcharge') ?></dt>
                        <dd class="font-bold text-amber-700 dark:text-amber-400" data-quote-surcharge>&mdash;</dd>
                    </div>
                    <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700">
                        <dt class="font-extrabold text-medical-900 dark:text-medical-100"><?= $view->t('booking.total') ?></dt>
                        <dd class="text-lg font-extrabold text-medical-700 dark:text-medical-300" data-quote-total>&mdash;</dd>
                    </div>
                    <div class="flex items-center justify-between" data-quote-deposit-row hidden>
                        <dt class="text-xs text-slate-500 dark:text-slate-400"><?= $view->t('payment.deposit_due') ?></dt>
                        <dd class="text-xs font-bold text-slate-700 dark:text-slate-300" data-quote-deposit>&mdash;</dd>
                    </div>
                </dl>
            </div>

            <?php if ($emergency !== ''): ?>
                <div class="cta-panel-dark mt-8">
                    <h2 class="block text-lg font-bold"><?= $view->t('booking.emergency_title') ?></h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/75"><?= $view->t('booking.emergency_body') ?></p>
                    <a href="tel:<?= $view->e($emergency) ?>"
                       class="mt-4 inline-flex items-center gap-2 text-lg font-bold text-medical-300 hover:underline">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <?= $view->e($emergency) ?>
                    </a>
                </div>
            <?php endif; ?>

            <p class="mt-6 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                <a href="<?= $view->url('my-booking') ?>" class="font-semibold text-medical-700 hover:underline dark:text-medical-300">
                    <?= $view->t('lookup.title') ?>
                </a>
            </p>
        </aside>

        <!-- ------------------------------ Form ------------------------------ -->
        <form method="post"
              action="<?= $view->url('book') ?>"
              class="card p-6 sm:p-8"
              data-booking-form
              novalidate>
            <?= $view->csrfField() ?>

            <?php if ($errors !== []): ?>
                <div class="alert-error mb-6" role="alert">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span><?= $view->t('validation.generic') ?></span>
                </div>
            <?php endif; ?>

            <!-- ---------- Step 1: what ---------- -->
            <fieldset class="grid gap-5">
                <legend class="mb-1 text-sm font-extrabold uppercase tracking-wider text-medical-700 dark:text-medical-300">
                    <?= $view->t('booking.step_details') ?>
                </legend>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="service_id">
                            <?= $view->t('booking.service') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <select class="select <?= $hasError('service_id') ? 'input-error' : '' ?>"
                                id="service_id" name="service_id" data-quote-input
                                <?= $hasError('service_id') ? 'aria-invalid="true" aria-describedby="service_id-error"' : '' ?>>
                            <option value=""><?= $view->t('booking.service_ph') ?></option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service->id ?>"
                                    <?= $view->attr((string) $service->id === $value('service_id', $preselected['service']), 'selected') ?>>
                                    <?= $view->e($service->title($locale)) ?><?= $service->hasPrice() ? ' — ' . $view->e($t->money($service->price)) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($hasError('service_id')): ?>
                            <span class="field-error" id="service_id-error"><?= $view->e($errors['service_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="label" for="package_id"><?= $view->t('booking.package') ?></label>
                        <select class="select" id="package_id" name="package_id" data-quote-input>
                            <option value=""><?= $view->t('booking.package_ph') ?></option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?= $package->id ?>"
                                    <?= $view->attr((string) $package->id === $value('package_id', $preselected['package']), 'selected') ?>>
                                    <?= $view->e($package->name($locale)) ?> — <?= $view->e($t->money($package->price)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint"><?= $view->t('form.optional') ?></span>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="doctor_id"><?= $view->t('booking.doctor') ?></label>
                        <select class="select" id="doctor_id" name="doctor_id" data-doctor-select data-quote-input>
                            <option value=""><?= $view->t('booking.doctor_any') ?></option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= $doctor->id ?>"
                                    <?= $view->attr((string) $doctor->id === $value('doctor_id', $preselected['doctor']), 'selected') ?>>
                                    <?= $view->e($doctor->name($locale)) ?> — <?= $view->e($doctor->specialtyLabel($locale)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="appointment_date">
                            <?= $view->t('booking.date') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input class="input <?= $hasError('appointment_date') ? 'input-error' : '' ?>"
                               type="date" id="appointment_date" name="appointment_date"
                               min="<?= $view->e($minDate) ?>" max="<?= $view->e($maxDate) ?>"
                               value="<?= $view->e($value('appointment_date')) ?>"
                               data-date-input required>
                        <?php if ($hasError('appointment_date')): ?>
                            <span class="field-error"><?= $view->e($errors['appointment_date']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Slot grid: rendered server-side as radios so it works
                     without JS, then re-rendered live with seat counts. -->
                <div class="field">
                    <span class="label">
                        <?= $view->t('booking.time') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                    </span>

                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4" data-slot-grid
                         role="radiogroup" aria-label="<?= $view->t('booking.time') ?>">
                        <?php foreach (TimeSlot::all() as $slot): ?>
                            <label class="slot" data-slot="<?= $view->e($slot->value) ?>">
                                <input type="radio" name="time_slot" value="<?= $view->e($slot->value) ?>"
                                       class="sr-only" data-slot-radio
                                       <?= $view->attr($value('time_slot') === $slot->value, 'checked') ?>>
                                <span class="text-sm font-bold"><?= $view->e($slot->label()) ?></span>
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400" data-slot-count></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="hint" data-slot-hint><?= $view->t('booking.select_date_first') ?></p>
                    <?php if ($hasError('time_slot')): ?>
                        <span class="field-error"><?= $view->e($errors['time_slot']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Express queue: the +20% surcharge product. -->
                <div class="field">
                    <span class="label"><?= $view->t('booking.tier') ?></span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="radio-card">
                            <input type="radio" name="queue_tier" value="standard" class="radio mt-0.5"
                                   data-quote-input <?= $view->attr($value('queue_tier', 'standard') !== 'express', 'checked') ?>>
                            <span>
                                <span class="block text-sm font-bold text-medical-900 dark:text-medical-100"><?= $view->t('booking.tier_standard') ?></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?= $view->t('queue_tier.standard') ?></span>
                            </span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="queue_tier" value="express" class="radio mt-0.5"
                                   data-quote-input <?= $view->attr($value('queue_tier') === 'express', 'checked') ?>>
                            <span>
                                <span class="block text-sm font-bold text-medical-900 dark:text-medical-100">
                                    <?= $view->t('booking.tier_express', ['percent' => $surchargeLabel]) ?>
                                </span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?= $view->t('booking.tier_express_hint') ?></span>
                            </span>
                        </label>
                    </div>
                </div>
            </fieldset>

            <!-- ---------- Step 2: who ---------- -->
            <fieldset class="mt-8 grid gap-5 border-t border-slate-200 pt-8 dark:border-slate-700">
                <legend class="mb-1 text-sm font-extrabold uppercase tracking-wider text-medical-700 dark:text-medical-300">
                    <?= $view->t('booking.step_patient') ?>
                </legend>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="patient_name">
                            <?= $view->t('booking.name') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input class="input <?= $hasError('patient_name') ? 'input-error' : '' ?>"
                               type="text" id="patient_name" name="patient_name" autocomplete="name"
                               placeholder="<?= $view->e($view->tRaw('booking.name_ph')) ?>"
                               value="<?= $view->e($value('patient_name')) ?>" required>
                        <?php if ($hasError('patient_name')): ?>
                            <span class="field-error"><?= $view->e($errors['patient_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="label" for="patient_phone">
                            <?= $view->t('booking.phone') ?> <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <input class="input <?= $hasError('patient_phone') ? 'input-error' : '' ?>"
                               type="tel" id="patient_phone" name="patient_phone"
                               autocomplete="tel" inputmode="tel"
                               placeholder="<?= $view->e($view->tRaw('booking.phone_ph')) ?>"
                               value="<?= $view->e($value('patient_phone')) ?>" required>
                        <span class="hint"><?= $view->t('booking.phone_hint') ?></span>
                        <?php if ($hasError('patient_phone')): ?>
                            <span class="field-error"><?= $view->e($errors['patient_phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="patient_email"><?= $view->t('booking.email') ?></label>
                    <input class="input <?= $hasError('patient_email') ? 'input-error' : '' ?>"
                           type="email" id="patient_email" name="patient_email" autocomplete="email"
                           placeholder="<?= $view->e($view->tRaw('booking.email_ph')) ?>"
                           value="<?= $view->e($value('patient_email')) ?>">
                    <span class="hint"><?= $view->t('booking.email_hint') ?></span>
                    <?php if ($hasError('patient_email')): ?>
                        <span class="field-error"><?= $view->e($errors['patient_email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="patient_notes"><?= $view->t('booking.notes') ?></label>
                    <textarea class="textarea" id="patient_notes" name="patient_notes" rows="3"
                              placeholder="<?= $view->e($view->tRaw('booking.notes_ph')) ?>"><?= $view->e($value('patient_notes')) ?></textarea>
                </div>
            </fieldset>

            <button type="submit" class="btn-primary mt-8 w-full py-4 text-base" data-submit>
                <span data-submit-label><?= $view->t('booking.submit') ?></span>
            </button>

            <p class="mt-4 text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                <?= $view->t('articles.disclaimer') ?>
            </p>
        </form>
    </div>
</section>
