<?php
/**
 * Patient Detail - Clinical Notes tab (spec §4.1/§4.2).
 *
 * Append-only end to end: there is no edit action anywhere on this
 * page for an existing note, because ClinicalNoteRepositoryInterface
 * exposes no update() to call - a correction is a new note, never a
 * change to an old one.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\User $user
 * @var \MediCareMini\Domain\Entity\Patient $patient
 * @var list<\MediCareMini\Domain\Entity\ClinicalNote> $notes
 * @var array<int, string> $noteContents id => decrypted plaintext
 * @var list<\MediCareMini\Domain\Entity\Encounter> $openEncounters
 * @var list<\MediCareMini\Domain\Enum\ClinicalNoteType> $noteTypes
 */

declare(strict_types=1);

use MediCareMini\Presentation\Support\PatientDetailAccess;

$adminPath     = '/' . $view->config->adminPath;
$allowedTypes  = PatientDetailAccess::allowedNoteTypes($user);
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div>
        <h2 class="text-base font-extrabold text-medical-900">Clinical notes</h2>

        <?php if ($notes === []): ?>
            <p class="mt-3 text-sm text-slate-500">No clinical notes recorded yet.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-3">
                <?php foreach ($notes as $note): ?>
                    <li class="rounded-xl border border-slate-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="badge"><?= $view->e($note->noteType->label()) ?></span>
                            <span class="text-xs text-slate-400">
                                <?= $view->e($note->authorName ?? 'Unknown') ?> &middot; <?= $view->e($note->createdAt->format('Y-m-d H:i')) ?>
                            </span>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-700"><?= $view->e($noteContents[$note->id] ?? '') ?></p>
                        <?php if ($note->vitals !== null && $note->vitals !== []): ?>
                            <dl class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-3">
                                <?php foreach ($note->vitals as $key => $value): ?>
                                    <div><dt class="text-[10px] font-bold uppercase text-slate-400"><?= $view->e(str_replace('_', ' ', (string) $key)) ?></dt><dd class="text-sm font-semibold text-slate-800"><?= $view->e((string) $value) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                        <?php if ($note->icdCode !== null): ?>
                            <p class="mt-2 font-mono text-[11px] text-slate-400">ICD: <?= $view->e($note->icdCode) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <aside>
        <?php if ($allowedTypes !== [] && $openEncounters !== []): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Add a note</h2>
                <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/notes') ?>" class="mt-4 grid gap-3">
                    <?= $view->csrfField() ?>
                    <div class="field">
                        <label class="label" for="note_encounter_id">Encounter</label>
                        <select class="select" id="note_encounter_id" name="encounter_id" required>
                            <?php foreach ($openEncounters as $encounter): ?>
                                <option value="<?= $encounter->id ?>"><?= $view->e($encounter->patientVisitNumber->value) ?> &middot; <?= $view->e($encounter->status->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="note_type">Type</label>
                        <select class="select" id="note_type" name="note_type" required>
                            <?php foreach ($allowedTypes as $type): ?>
                                <option value="<?= $view->e($type->value) ?>"><?= $view->e($type->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="content">Note</label>
                        <textarea class="textarea" id="content" name="content" rows="4" required></textarea>
                    </div>
                    <details class="text-xs">
                        <summary class="cursor-pointer font-bold text-slate-500">Vitals (only saved for a Vitals note)</summary>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <input class="input" type="number" name="bp_systolic" placeholder="Systolic">
                            <input class="input" type="number" name="bp_diastolic" placeholder="Diastolic">
                            <input class="input" type="number" name="heart_rate" placeholder="HR (bpm)">
                            <input class="input" type="text" name="temperature_c" placeholder="Temp (&deg;C)">
                            <input class="input" type="number" name="respiratory_rate" placeholder="RR (/min)">
                            <input class="input" type="number" name="spo2" placeholder="SpO2 (%)">
                        </div>
                    </details>
                    <div class="field">
                        <label class="label" for="note_icd_code">ICD code (optional)</label>
                        <input class="input" type="text" id="note_icd_code" name="icd_code">
                    </div>
                    <button type="submit" class="btn-primary btn-sm w-full">Save note</button>
                </form>
            </div>
        <?php elseif ($allowedTypes !== []): ?>
            <div class="card-pad">
                <p class="text-sm text-slate-500">This patient has no open encounter to attach a note to.</p>
            </div>
        <?php endif; ?>
    </aside>
</div>
