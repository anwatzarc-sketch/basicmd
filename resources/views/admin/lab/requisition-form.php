<?php
/**
 * New lab requisition.
 *
 * Two steps, not one: identify the encounter, then describe the test.
 * The encounter comes first because a requisition without one has
 * nowhere to hang (see LabController::requisitionForm()'s docblock), and
 * a form that lets you fill in nine fields before telling you that is a
 * form that wastes a technician's time at the bench.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\Encounter|null $encounter
 * @var bool $notFound
 * @var string $searchTerm
 * @var list<\Aster\Domain\Entity\Encounter> $active
 * @var list<\Aster\Domain\Entity\LabPanel> $panels
 * @var array<string,string> $old
 */

declare(strict_types=1);

$val = static fn (string $key, string $default = ''): string => (string) ($old[$key] ?? $default);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-base font-extrabold text-medical-900">New laboratory requisition</h2>
        <p class="mt-1 text-sm text-slate-500">Raised against an open encounter; an accession number is issued on save.</p>
    </div>
    <a href="<?= $view->adminUrl('lab') ?>" class="btn-secondary btn-sm">Back to queue</a>
</div>

<!-- Step 1 - find the encounter --------------------------------------- -->
<div class="card-pad mt-6">
    <h3 class="panel-title text-xs">1 &middot; Encounter</h3>

    <form method="get" class="mt-3 flex flex-wrap items-end gap-3">
        <div class="field min-w-[16rem]">
            <label class="label" for="visit">Visit number</label>
            <input class="input font-mono" type="text" id="visit" name="visit" value="<?= $view->e($searchTerm) ?>"
                   placeholder="VIS-20260919-00001" <?= $encounter === null ? 'autofocus' : '' ?>>
        </div>
        <button type="submit" class="btn-secondary btn-sm">Find</button>
    </form>

    <?php if ($notFound): ?>
        <div class="alert-error mt-4" role="alert">
            <span class="flex-1">No encounter found for that visit number.</span>
        </div>
    <?php endif; ?>

    <?php if ($encounter === null): ?>
        <?php if ($active === []): ?>
            <p class="mt-4 text-sm text-slate-500">No active encounters right now.</p>
        <?php else: ?>
            <p class="mt-5 text-xs font-bold uppercase tracking-wider text-slate-500">Or pick an active encounter</p>
            <ul class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($active as $option): ?>
                    <li>
                        <a class="soft-card flex items-center justify-between gap-3 text-sm"
                           href="<?= $view->adminUrl('lab/requisitions/create?visit=' . urlencode($option->patientVisitNumber->value)) ?>">
                            <span>
                                <span class="block font-semibold text-slate-900"><?= $view->e($option->patientName ?? 'Unknown') ?></span>
                                <span class="block font-mono text-[11px] text-slate-400"><?= $view->e($option->patientVisitNumber->value) ?></span>
                            </span>
                            <span class="<?= $view->e($option->status->chipClass()) ?>"><?= $view->e($option->status->label()) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Step 2 - describe the test ---------------------------------------- -->
<?php if ($encounter !== null): ?>
    <form method="post" action="<?= $view->adminUrl('lab/requisitions') ?>" class="card-pad mt-6 grid gap-5">
        <?= $view->csrfField() ?>
        <input type="hidden" name="encounter_id" value="<?= (int) $encounter->id ?>">

        <div>
            <h3 class="panel-title text-xs">2 &middot; Requested panel</h3>
            <p class="mt-2 text-sm text-slate-600">
                <b class="text-slate-900"><?= $view->e($encounter->patientName ?? 'Unknown patient') ?></b>
                &middot; <span class="font-mono text-xs"><?= $view->e($encounter->patientVisitNumber->value) ?></span>
                &middot; <?= $view->e($encounter->visitType->label()) ?>
                <?php if ($encounter->locationLabel !== null): ?>
                    &middot; <?= $view->e($encounter->locationLabel) ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($panels === []): ?>
            <div class="alert-warning" role="alert">
                <span class="flex-1">
                    The test directory is empty, so there is nothing to order.
                    <a class="underline" href="<?= $view->adminUrl('lab/catalog') ?>">Add a panel first</a>.
                </span>
            </div>
        <?php else: ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="panel_code">Panel <span class="text-rose-500" aria-hidden="true">*</span></label>
                    <select class="select" id="panel_code" name="panel_code" required data-lab-panel-picker>
                        <?php foreach ($panels as $panel): ?>
                            <option value="<?= $view->e($panel->panelCode) ?>"
                                    data-specimen="<?= $view->e($panel->specimenType) ?>"
                                    <?= $view->attr($val('panel_code') === $panel->panelCode, 'selected') ?>>
                                <?= $view->e($panel->panelName) ?> (<?= $view->e($panel->department) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Reference intervals come from the directory and are copied onto the report when results are released.</span>
                </div>

                <div class="field">
                    <label class="label" for="icd_code">ICD code <span class="text-rose-500" aria-hidden="true">*</span></label>
                    <input class="input font-mono" type="text" id="icd_code" name="icd_code" required maxlength="16"
                           value="<?= $view->e($val('icd_code')) ?>" placeholder="e.g. D50.9">
                    <span class="hint">The indication this panel is being requested for.</span>
                </div>

                <div class="field">
                    <label class="label" for="specimen_type">Specimen type</label>
                    <input class="input" type="text" id="specimen_type" name="specimen_type" maxlength="80"
                           value="<?= $view->e($val('specimen_type', $panels[0]->specimenType)) ?>" data-lab-specimen-target>
                    <span class="hint">Defaults to the panel's usual matrix; change it if the specimen differs.</span>
                </div>

                <div class="field">
                    <label class="label" for="clinical_location">Collection point / ward</label>
                    <input class="input" type="text" id="clinical_location" name="clinical_location" maxlength="120"
                           value="<?= $view->e($val('clinical_location', $encounter->locationLabel ?? '')) ?>"
                           placeholder="Outpatient clinic, ward name or bay">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">
                    An accession number (LAB-YYYYMMDD-NNNNN) is issued on save and becomes the specimen label.
                </p>
                <button type="submit" class="btn-primary btn-sm">Raise requisition</button>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>
