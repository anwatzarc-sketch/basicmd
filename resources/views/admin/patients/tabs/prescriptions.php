<?php
/**
 * Patient Detail - Prescriptions tab (spec §4.1).
 *
 * Dispensing is the bare is_dispensed toggle spec §6 calls for - there
 * is no dispensed_by/dispensed_at to show because those columns do not
 * exist (e_prescriptions has never had them). See
 * PatientDetailAccess::canDispense()'s own docblock for why physician
 * and nurse are the roles that can flip it.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User $user
 * @var \Aster\Domain\Entity\Patient $patient
 * @var list<\Aster\Domain\Entity\EPrescription> $prescriptions
 * @var list<\Aster\Domain\Entity\Encounter> $openEncounters
 */

declare(strict_types=1);

use Aster\Presentation\Support\PatientDetailAccess;

$adminPath = $view->config->adminPath;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div>
        <h2 class="text-base font-extrabold text-medical-900">Prescriptions</h2>

        <?php if ($prescriptions === []): ?>
            <p class="mt-3 text-sm text-slate-500">No prescriptions recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Prescriber</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx): ?>
                            <tr>
                                <td class="font-semibold text-slate-900"><?= $view->e($rx->medicationName) ?></td>
                                <td class="text-slate-500"><?= $view->e($rx->dosage) ?></td>
                                <td class="text-slate-500"><?= $view->e($rx->frequency) ?></td>
                                <td class="text-slate-500"><?= $rx->durationDays ?> day(s)</td>
                                <td class="text-slate-500"><?= $view->e($rx->prescriberName ?? '-') ?></td>
                                <td>
                                    <span class="badge <?= $rx->isDispensed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' ?>">
                                        <?= $rx->isDispensed ? 'Dispensed' : 'Pending' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$rx->isDispensed && PatientDetailAccess::canDispense($user)): ?>
                                        <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/prescriptions/' . $rx->id . '/dispense') ?>">
                                            <?= $view->csrfField() ?>
                                            <button type="submit" class="btn-ghost btn-sm">Mark dispensed</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <aside>
        <?php if (PatientDetailAccess::canWritePrescriptions($user)): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Write a prescription</h2>
                <?php if ($openEncounters === []): ?>
                    <p class="mt-2 text-xs text-amber-700">This patient has no open encounter to prescribe against.</p>
                <?php else: ?>
                    <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/prescriptions') ?>" class="mt-4 grid gap-3">
                        <?= $view->csrfField() ?>
                        <div class="field">
                            <label class="label" for="rx_encounter_id">Encounter</label>
                            <select class="select" id="rx_encounter_id" name="encounter_id" required>
                                <?php foreach ($openEncounters as $encounter): ?>
                                    <option value="<?= $encounter->id ?>"><?= $view->e($encounter->patientVisitNumber->value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="medication_name">Medication</label>
                            <input class="input" type="text" id="medication_name" name="medication_name" required>
                        </div>
                        <div class="field">
                            <label class="label" for="dosage">Dosage</label>
                            <input class="input" type="text" id="dosage" name="dosage" required placeholder="e.g. 500mg">
                        </div>
                        <div class="field">
                            <label class="label" for="frequency">Frequency</label>
                            <input class="input" type="text" id="frequency" name="frequency" required placeholder="e.g. Twice daily">
                        </div>
                        <div class="field">
                            <label class="label" for="duration_days">Duration (days)</label>
                            <input class="input" type="number" id="duration_days" name="duration_days" min="1" required>
                        </div>
                        <div class="field">
                            <label class="label" for="rx_icd_code">ICD code</label>
                            <input class="input" type="text" id="rx_icd_code" name="icd_code" required>
                        </div>
                        <button type="submit" class="btn-primary btn-sm w-full">Save prescription</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
