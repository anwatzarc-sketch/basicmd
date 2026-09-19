<?php
/**
 * Test panel create/edit, including its analyte grid.
 *
 * The analyte grid uses the same whole-row post shape and the same
 * app.js behaviour as the result-entry grid - one grid component, two
 * screens - so a change to how rows are added or removed lands on both
 * at once instead of drifting apart.
 *
 * Saving replaces the panel's analytes wholesale
 * (LabCatalogRepositoryInterface::replaceParameters()), which is why
 * every existing row is rendered as a form field rather than as text
 * with an edit link: what is submitted IS the new list.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\LabPanel|null $panel
 * @var array<string,string> $old
 */

declare(strict_types=1);

use Aster\Domain\Entity\LabPanelParameter;

$action = $panel === null
    ? $view->adminUrl('lab/catalog')
    : $view->adminUrl('lab/catalog/' . $panel->id);

$val    = static fn (string $key, mixed $current = ''): string => (string) ($old[$key] ?? $current ?? '');
$number = static fn (?float $value): string => $value === null ? '' : LabPanelParameter::number($value);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h2 class="text-base font-extrabold text-medical-900">
        <?= $panel === null ? 'New test panel' : $view->e($panel->panelName) ?>
    </h2>
    <a href="<?= $view->adminUrl('lab/catalog') ?>" class="btn-secondary btn-sm">Back to directory</a>
</div>

<form method="post" action="<?= $action ?>" class="mt-6 grid gap-6" data-lab-entry>
    <?= $view->csrfField() ?>

    <section class="card-pad grid gap-5">
        <h3 class="panel-title text-xs">Panel</h3>

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="field">
                <label class="label" for="panel_name">Name <span class="text-rose-500" aria-hidden="true">*</span></label>
                <input class="input" type="text" id="panel_name" name="panel_name" required maxlength="190"
                       value="<?= $view->e($val('panel_name', $panel?->panelName)) ?>"
                       placeholder="Complete Blood Count (CBC)">
            </div>

            <div class="field">
                <label class="label" for="panel_code">Code <span class="text-rose-500" aria-hidden="true">*</span></label>
                <input class="input font-mono uppercase" type="text" id="panel_code" name="panel_code" required
                       maxlength="32" pattern="[A-Za-z0-9_-]{2,32}"
                       value="<?= $view->e($val('panel_code', $panel?->panelCode)) ?>" placeholder="CBC">
                <span class="hint">
                    2-32 letters, digits, dash or underscore. Requisitions carry this code, so changing it on an
                    established panel detaches it from the orders already placed under the old one.
                </span>
            </div>

            <div class="field">
                <label class="label" for="department">Department</label>
                <input class="input" type="text" id="department" name="department" maxlength="80"
                       value="<?= $view->e($val('department', $panel?->department ?? 'Clinical Chemistry')) ?>">
            </div>

            <div class="field">
                <label class="label" for="specimen_type">Usual specimen</label>
                <input class="input" type="text" id="specimen_type" name="specimen_type" maxlength="80"
                       value="<?= $view->e($val('specimen_type', $panel?->specimenType ?? 'Serum (SST tube)')) ?>">
            </div>

            <div class="field sm:col-span-2">
                <label class="label" for="report_title">Report heading</label>
                <input class="input" type="text" id="report_title" name="report_title" maxlength="190"
                       value="<?= $view->e($val('report_title', $panel?->reportTitle)) ?>"
                       placeholder="Haematology - Complete Blood Count (CBC) with Differential">
                <span class="hint">Printed above the results table. Defaults to the panel name if left blank.</span>
            </div>

            <div class="field">
                <label class="label" for="methodology">Default methodology</label>
                <input class="input" type="text" id="methodology" name="methodology" maxlength="190"
                       value="<?= $view->e($val('methodology', $panel?->methodology)) ?>">
            </div>

            <div class="field">
                <label class="label" for="sort_order">Listing order</label>
                <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="9999"
                       value="<?= $view->e($val('sort_order', (string) ($panel?->sortOrder ?? 0))) ?>">
            </div>

            <div class="field sm:col-span-2">
                <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                    <input class="checkbox" type="checkbox" name="is_active" value="1"
                           <?= $view->attr($panel === null || $panel->isActive, 'checked') ?>>
                    Available for new requisitions
                </label>
                <span class="hint">
                    Unticking retires the panel. Orders already placed under it stay resultable and printable.
                </span>
            </div>
        </div>
    </section>

    <section class="card-pad">
        <h3 class="panel-title text-xs">Analytes</h3>

        <div class="table-wrap mt-4">
            <table class="table min-w-[52rem]">
                <thead>
                    <tr>
                        <th class="w-[22rem]">Parameter</th>
                        <th class="w-28">Units</th>
                        <th class="w-24">Ref. min</th>
                        <th class="w-24">Ref. max</th>
                        <th class="w-40">Normal reads as</th>
                        <th class="w-44">Method</th>
                        <th class="w-10"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody data-lab-rows>
                    <?php foreach ($panel?->parameters ?? [] as $index => $parameter): ?>
                        <tr data-lab-row>
                            <td>
                                <input class="input" type="text" required name="rows[<?= (int) $index ?>][name]"
                                       value="<?= $view->e($parameter->parameterName) ?>" aria-label="Parameter name">
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][unit]"
                                       value="<?= $view->e($parameter->unit) ?>" aria-label="Units">
                            </td>
                            <td>
                                <input class="input font-mono" type="text" inputmode="decimal" name="rows[<?= (int) $index ?>][min]"
                                       value="<?= $view->e($number($parameter->referenceMin)) ?>" aria-label="Reference minimum">
                            </td>
                            <td>
                                <input class="input font-mono" type="text" inputmode="decimal" name="rows[<?= (int) $index ?>][max]"
                                       value="<?= $view->e($number($parameter->referenceMax)) ?>" aria-label="Reference maximum">
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][text]"
                                       value="<?= $view->e($parameter->referenceText ?? '') ?>"
                                       placeholder="Negative" aria-label="Qualitative reference">
                            </td>
                            <td>
                                <input class="input" type="text" name="rows[<?= (int) $index ?>][method]"
                                       value="<?= $view->e($parameter->methodology) ?>" aria-label="Methodology">
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
            <button type="button" class="btn-secondary btn-sm" data-lab-add>Add analyte</button>
            <p class="text-xs text-slate-500">
                Leave both bounds blank for a qualitative test and state what normal reads as instead
                &mdash; "Negative", "Yellow / Clear".
            </p>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary btn-sm">Save panel</button>
    </div>
</form>

<template data-lab-row-template>
    <tr data-lab-row>
        <td><input class="input" type="text" required name="rows[__INDEX__][name]" aria-label="Parameter name"></td>
        <td><input class="input" type="text" name="rows[__INDEX__][unit]" aria-label="Units"></td>
        <td><input class="input font-mono" type="text" inputmode="decimal" name="rows[__INDEX__][min]" aria-label="Reference minimum"></td>
        <td><input class="input font-mono" type="text" inputmode="decimal" name="rows[__INDEX__][max]" aria-label="Reference maximum"></td>
        <td><input class="input" type="text" name="rows[__INDEX__][text]" placeholder="Negative" aria-label="Qualitative reference"></td>
        <td><input class="input" type="text" name="rows[__INDEX__][method]" aria-label="Methodology"></td>
        <td class="text-right">
            <button type="button" class="btn-ghost btn-sm !px-2 text-rose-600" data-lab-remove aria-label="Remove this parameter">&times;</button>
        </td>
    </tr>
</template>
