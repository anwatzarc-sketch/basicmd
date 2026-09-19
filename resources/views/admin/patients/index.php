<?php
/**
 * MPI lookup (FRS 10.1).
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var string $term
 * @var list<\MediCareMini\Domain\Entity\Patient> $results
 */

declare(strict_types=1);
?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div class="field">
            <label class="label" for="q">National ID, phone, PID, or name</label>
            <input class="input" type="text" id="q" name="q" value="<?= $view->e($term) ?>"
                   placeholder="e.g. +251911223344 or PID-2026-00001" autofocus>
        </div>
        <button type="submit" class="btn-primary btn-sm">Search</button>
    </form>
    <a href="<?= $view->adminUrl('patients/create') ?>" class="btn-secondary btn-sm">Register patient</a>
</div>

<?php if ($term !== ''): ?>
    <div class="card mt-6">
        <?php if ($results === []): ?>
            <p class="p-10 text-center text-sm text-slate-500">
                No matching patient. If this is genuinely a new person,
                <a href="<?= $view->adminUrl('patients/create') ?>" class="font-bold text-medical-700">register them</a>.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>PID</th><th>Name</th><th>Phone</th><th>Date of birth</th><th>National ID</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $patient): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= $view->e($patient->pid->value) ?></td>
                                <td class="font-semibold text-slate-900"><?= $view->e($patient->fullName()) ?></td>
                                <td><?= $view->e($patient->phoneNumber->e164) ?></td>
                                <td><?= $view->e($patient->dateOfBirth->format('Y-m-d')) ?></td>
                                <td class="text-slate-500"><?= $view->e($patient->nationalId ?? '-') ?></td>
                                <td class="text-right">
                                    <a href="<?= $view->adminUrl('patients/' . $patient->id) ?>" class="btn-ghost btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
