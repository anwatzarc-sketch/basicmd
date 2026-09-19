<?php
/**
 * Structured lab result entry.
 *
 * The grid posts whole rows - rows[3][name], rows[3][value], ... - not
 * parallel name[]/value[] arrays. LabReportService::compose() explains
 * why at length; the short version is that a shifted lab grid prints a
 * haemoglobin value against a platelet reference range, and whole rows
 * make that unrepresentable. Any markup change here has to keep that
 * shape.
 *
 * The flag beside each value is recomputed live by app.js as a preview
 * only. What is printed and what is stored is always derived server-side
 * by LabResultFlag::evaluate(), so a browser with JavaScript disabled
 * loses the preview and nothing else.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\DiagnosticOrder $order
 * @var \MediCareMini\Domain\DTO\LabReport $report
 * @var \MediCareMini\Domain\Entity\LabPanel|null $panel
 * @var list<\MediCareMini\Domain\Entity\LabPanel> $panels
 * @var bool $released
 */

declare(strict_types=1);

use MediCareMini\Domain\Entity\LabPanelParameter;

$number = static fn (?float $value): string => $value === null ? '' : LabPanelParameter::number($value);

$collectedValue = $order->collectedAt?->format('Y-m-d\TH:i') ?? '';
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h2 class="text-base font-extrabold text-medical-900">Record laboratory results</h2>
        <p class="mt-1 font-mono text-xs text-slate-500">
            <?= $view->e($order->accessionNumber?->value ?? ('Order #' . $order->id)) ?>
            &middot; <?= $view->e($order->testName) ?>
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= $view->adminUrl('lab') ?>" class="btn-secondary btn-sm">Back to queue</a>
        <?php if ($order->hasResults()): ?>
            <a href="<?= $view->adminUrl('lab/orders/' . $order->id . '/report') ?>" class="btn-ghost btn-sm">View report sheet</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($released): ?>
    <div class="alert-warning mt-5" role="alert">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span class="flex-1">
            This report has already been released and may have been handed to the patient.
            Saving again amends the released result and is recorded in the audit trail.
        </span>
    </div>
<?php endif; ?>

<form method="post" action="<?= $view->adminUrl('lab/orders/' . $order->id . '/results') ?>"
      class="mt-6 grid gap-6" data-lab-entry>
    <?= $view->csrfField() ?>

    <!-- 1 - Patient, specimen and requisition ------------------------- -->
    <section class="card-pad">
        <h3 class="panel-title text-xs">1 &middot; Patient &amp; specimen</h3>

        <dl class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <dt class="dt-label text-[0.7rem]">Patient</dt>
                <dd class="mt-1 text-sm font-bold text-slate-900"><?= $view->e($order->patientName ?? 'Unknown') ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Patient ID</dt>
                <dd class="mt-1 font-mono text-sm text-slate-700"><?= $view->e($order->patientPid ?? '-') ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Age / sex</dt>
                <dd class="mt-1 text-sm text-slate-700"><?= $view->e($order->patientAgeGender()) ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Visit</dt>
                <dd class="mt-1 font-mono text-sm text-slate-700"><?= $view->e($order->visitNumber ?? '-') ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Ordered by</dt>
                <dd class="mt-1 text-sm text-slate-700"><?= $view->e($order->physicianName ?? '-') ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Indication</dt>
                <dd class="mt-1 font-mono text-sm text-slate-700">ICD <?= $view->e($order->icdCode) ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Raised</dt>
                <dd class="mt-1 font-mono text-sm text-slate-700"><?= $view->e($order->createdAt->format('Y-m-d H:i')) ?></dd>
            </div>
            <div>
                <dt class="dt-label text-[0.7rem]">Status</dt>
                <dd class="mt-1"><span class="<?= $view->e($order->status->chipClass()) ?>"><?= $view->e($order->status->labLabel()) ?></span></dd>
            </div>
        </dl>

        <div class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="field">
                <label class="label" for="specimen_type">Specimen type</label>
                <input class="input" type="text" id="specimen_type" name="specimen_type" maxlength="80"
                       value="<?= $view->e($order->specimenType ?? $panel?->specimenType ?? '') ?>">
            </div>
            <div class="field">
                <label class="label" for="specimen_barcode">Specimen label</label>
                <input class="input font-mono" type="text" id="specimen_barcode" name="specimen_barcode" maxlength="40"
                       value="<?= $view->e($order->barcodeValue() ?? '') ?>">
                <span class="hint">Defaults to the accession number; overwrite for a pre-printed tube.</span>
            </div>
            <div class="field">
                <label class="label" for="collected_at">Collected at</label>
                <input class="input" type="datetime-local" id="collected_at" name="collected_at"
                       value="<?= $view->e($collectedValue) ?>">
            </div>
            <div class="field">
                <label class="label" for="clinical_location">Collection point / ward</label>
                <input class="input" type="text" id="clinical_location" name="clinical_location" maxlength="120"
                       value="<?= $view->e($order->clinicalLocation ?? '') ?>">
            </div>
        </div>
    </section>

    <!-- 2 - Result matrix --------------------------------------------- -->
    <section class="card-pad">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h3 class="panel-title text-xs">2 &middot; Findings</h3>

            <?php if ($panels !== []): ?>
                <div class="field w-full max-w-sm">
                    <label class="label" for="preset">Load a panel from the directory</label>
                    <div class="flex gap-2">
                        <select class="select" id="preset" data-lab-preset>
                            <?php foreach ($panels as $option): ?>
                                <option value="<?= $view->e($option->panelCode) ?>"
                                        <?= $view->attr($option->panelCode === $order->panelCode, 'selected') ?>>
                                    <?= $view->e($option->panelName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-secondary btn-sm shrink-0" data-lab-preset-load
                                data-endpoint="<?= $view->adminUrl('lab/panels') ?>">
                            Replace grid
                        </button>
                    </div>
                    <span class="hint">Replaces every row below, including anything already typed.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="field mt-4">
            <label class="label" for="report_title">Report heading</label>
            <input class="input" type="text" id="report_title" name="report_title" maxlength="190"
                   value="<?= $view->e($report->title) ?>">
        </div>

        <div class="table-wrap mt-4">
            <table class="table min-w-[56rem]">
                <thead>
                    <tr>
                        <th class="w-[22rem]">Parameter</th>
                        <th class="w-36">Result</th>
                        <th class="w-28">Flag</th>
                        <th class="w-28">Units</th>
                        <th class="w-24">Ref. min</th>
                        <th class="w-24">Ref. max</th>
                        <th class="w-40">Normal reads as</th>
                        <th class="w-44">Method</th>
                        <th class="w-10"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody data-lab-rows>
                    <?php foreach ($report->lines as $index => $line): ?>
                        <?php $flag = $line->flag(); ?>
                        <tr data-lab-row>
                            <td>
                                <input class="input" type="text" required
                                       name="rows[<?= (int) $index ?>][name]"
                                       value="<?= $view->e($line->parameterName) ?>"
                                       aria-label="Parameter name">
                            </td>
                            <td>
                                <input class="input text-center font-mono font-bold" type="text"
                                       name="rows[<?= (int) $index ?>][value]"
                                       value="<?= $view->e($line->value) ?>"
                                       aria-label="Result value" data-lab-value>
                            </td>
                            <td>
                                <span class="<?= $view->e($flag->chipClass()) ?>" data-lab-flag
                                      <?= $line->isReported() ? '' : 'hidden' ?>>
                                    <?= $view->e($flag->label()) ?>
                                </span>
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][unit]"
                                       value="<?= $view->e($line->unit) ?>" aria-label="Units">
                            </td>
                            <td>
                                <input class="input font-mono" type="text" inputmode="decimal"
                                       name="rows[<?= (int) $index ?>][min]"
                                       value="<?= $view->e($number($line->referenceMin)) ?>"
                                       aria-label="Reference minimum" data-lab-min>
                            </td>
                            <td>
                                <input class="input font-mono" type="text" inputmode="decimal"
                                       name="rows[<?= (int) $index ?>][max]"
                                       value="<?= $view->e($number($line->referenceMax)) ?>"
                                       aria-label="Reference maximum" data-lab-max>
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][text]"
                                       value="<?= $view->e($line->referenceText ?? '') ?>"
                                       placeholder="Negative" aria-label="Qualitative reference" data-lab-text>
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][method]"
                                       value="<?= $view->e($line->methodology) ?>" aria-label="Methodology">
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn-ghost btn-sm !px-2 text-rose-600" data-lab-remove
                                        aria-label="Remove this parameter">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="btn-secondary btn-sm" data-lab-add>Add parameter</button>
            <p class="text-xs text-slate-500">
                Leave a reference bound blank for a qualitative parameter and state what normal reads as instead.
            </p>
        </div>
    </section>

    <!-- 3 - Impression ------------------------------------------------ -->
    <section class="card-pad">
        <h3 class="panel-title text-xs">3 &middot; Comments &amp; diagnostic impression</h3>
        <div class="field mt-3">
            <label class="label sr-only" for="impression">Impression</label>
            <textarea class="textarea" id="impression" name="impression" rows="4"
                      placeholder="Interpretation, correlation advised, repeat recommended, critical value phoned through to..."><?= $view->e($report->impression) ?></textarea>
            <span class="hint">
                Written per patient. Nothing is pre-filled here on purpose - a canned impression is text that gets signed without being read.
            </span>
        </div>
    </section>

    <!-- Actions -------------------------------------------------------- -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            Releasing marks the report printable and records you as the technologist who verified it.
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" name="action" value="draft" class="btn-secondary btn-sm">Save draft</button>
            <button type="submit" name="action" value="release" class="btn-primary btn-sm">Verify &amp; release</button>
        </div>
    </div>
</form>

<!--
    Row template for "Add parameter". __INDEX__ is substituted by app.js
    with a counter that starts above every index rendered above, so a new
    row can never collide with an existing one and quietly overwrite it.
-->
<template data-lab-row-template>
    <tr data-lab-row>
        <td><input class="input" type="text" required name="rows[__INDEX__][name]" aria-label="Parameter name"></td>
        <td><input class="input text-center font-mono font-bold" type="text" name="rows[__INDEX__][value]" aria-label="Result value" data-lab-value></td>
        <td><span class="chip--built" data-lab-flag hidden></span></td>
        <td><input class="input" type="text" name="rows[__INDEX__][unit]" aria-label="Units"></td>
        <td><input class="input font-mono" type="text" inputmode="decimal" name="rows[__INDEX__][min]" aria-label="Reference minimum" data-lab-min></td>
        <td><input class="input font-mono" type="text" inputmode="decimal" name="rows[__INDEX__][max]" aria-label="Reference maximum" data-lab-max></td>
        <td><input class="input" type="text" name="rows[__INDEX__][text]" placeholder="Negative" aria-label="Qualitative reference" data-lab-text></td>
        <td><input class="input" type="text" name="rows[__INDEX__][method]" aria-label="Methodology"></td>
        <td class="text-right">
            <button type="button" class="btn-ghost btn-sm !px-2 text-rose-600" data-lab-remove aria-label="Remove this parameter">&times;</button>
        </td>
    </tr>
</template>
