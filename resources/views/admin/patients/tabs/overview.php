<?php
/**
 * Patient Detail - Overview tab.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User $user
 * @var \Aster\Domain\Entity\Patient $patient
 * @var list<string> $allergies
 * @var list<\Aster\Domain\Entity\Encounter> $encounters
 */

declare(strict_types=1);

use Aster\Presentation\Support\PatientDetailAccess;

$adminPath = '/' . $view->config->adminPath;
?>
<div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">

    <div class="grid gap-6">
        <div>
            <h2 class="text-base font-extrabold text-medical-900">Contact &amp; identity</h2>
            <dl class="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="stat-label">Email</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->email ?? '-') ?></dd></div>
                <div><dt class="stat-label">National ID</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->nationalId ?? '-') ?></dd></div>
                <div><dt class="stat-label">Address</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->address ?? '-') ?></dd></div>
                <div><dt class="stat-label">Emergency contact</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->emergencyContactName ?? '-') ?></dd></div>
                <div><dt class="stat-label">Emergency phone</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->emergencyContactPhone ?? '-') ?></dd></div>
                <div><dt class="stat-label">Registered</dt><dd class="mt-1 font-semibold text-slate-900"><?= $view->e($patient->createdAt->format('Y-m-d')) ?></dd></div>
            </dl>
        </div>

        <?php if (PatientDetailAccess::canEditDemographics($user)): ?>
            <details class="rounded-xl border border-slate-100 p-4">
                <summary class="cursor-pointer text-sm font-bold text-medical-700">Edit contact details</summary>
                <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id) ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?= $view->csrfField() ?>
                    <div class="field">
                        <label class="label" for="first_name">First name</label>
                        <input class="input" type="text" id="first_name" name="first_name" required value="<?= $view->e($patient->firstName) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="last_name">Last name</label>
                        <input class="input" type="text" id="last_name" name="last_name" required value="<?= $view->e($patient->lastName) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="phone_number">Phone</label>
                        <input class="input" type="text" id="phone_number" name="phone_number" required value="<?= $view->e($patient->phoneNumber->e164) ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" value="<?= $view->e($patient->email ?? '') ?>">
                    </div>
                    <div class="field sm:col-span-2">
                        <label class="label" for="address">Address</label>
                        <input class="input" type="text" id="address" name="address" value="<?= $view->e($patient->address ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="emergency_contact_name">Emergency contact</label>
                        <input class="input" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= $view->e($patient->emergencyContactName ?? '') ?>">
                    </div>
                    <div class="field">
                        <label class="label" for="emergency_contact_phone">Emergency phone</label>
                        <input class="input" type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $view->e($patient->emergencyContactPhone ?? '') ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary btn-sm">Save changes</button>
                    </div>
                </form>
            </details>
        <?php endif; ?>

        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-base font-extrabold text-medical-900">Recent encounters</h2>
                <a href="<?= $view->e($adminPath . '/patients/' . $patient->id . '?tab=encounters') ?>" class="text-xs font-bold text-medical-600 hover:underline">
                    View all
                </a>
            </div>
            <?php if ($encounters === []): ?>
                <p class="mt-3 text-sm text-slate-500">No encounters recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap mt-4">
                    <table class="table">
                        <thead><tr><th>Visit</th><th>Type</th><th>Status</th><th>Started</th></tr></thead>
                        <tbody>
                            <?php foreach ($encounters as $encounter): ?>
                                <tr>
                                    <td class="font-mono text-xs">
                                        <a href="<?= $view->e($adminPath . '/encounters/' . $encounter->id) ?>" class="text-medical-700 hover:underline">
                                            <?= $view->e($encounter->patientVisitNumber->value) ?>
                                        </a>
                                    </td>
                                    <td><?= $view->e($encounter->visitType->label()) ?></td>
                                    <td><span class="<?= $view->e($encounter->status->chipClass()) ?>"><?= $view->e($encounter->status->label()) ?></span></td>
                                    <td class="text-slate-500"><?= $view->e($encounter->createdAt->format('Y-m-d H:i')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="grid gap-6">
        <?php if (PatientDetailAccess::canProvisionPortalAccess($user)): ?>
            <div class="card-pad">
                <h2 class="text-base font-extrabold text-medical-900">Patient portal access</h2>
                <p class="mt-2 text-xs text-slate-500">
                    Generates a temporary password for <?= $view->e($patient->fullName()) ?> to sign in at
                    the patient portal with their Patient ID. Resets any existing portal password.
                </p>
                <form method="post" action="<?= $view->e($adminPath . '/patients/' . $patient->id . '/portal-access') ?>" class="mt-4"
                      data-confirm="Give this patient portal access? This resets any existing portal password."
                      data-confirm-title="Confirm portal access" data-confirm-action="Provision access" data-confirm-variant="primary">
                    <?= $view->csrfField() ?>
                    <button type="submit" class="btn-secondary btn-sm w-full">Provision / reset portal access</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
</div>
