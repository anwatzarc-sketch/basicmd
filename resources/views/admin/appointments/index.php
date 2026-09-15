<?php
/**
 * Appointment queue.
 *
 * @var \Aster\Presentation\View\View $view
 * @var list<\Aster\Domain\Entity\Appointment> $appointments
 * @var array $filters
 * @var array<int,string> $doctors
 * @var list<\Aster\Domain\Enum\AppointmentStatus> $statuses
 * @var array<string,int|bool> $pagination
 * @var bool $canEdit
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = $view->config->adminPath;

$query = static function (array $overrides) use ($filters): string {
    $params = array_filter(
        array_merge(array_filter($filters, static fn ($v) => $v !== null && $v !== ''), $overrides),
        static fn ($v): bool => $v !== null && $v !== '',
    );

    return $params === [] ? '' : '?' . http_build_query($params);
};
?>

<form method="get" class="card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-auto-filter>
    <div class="field lg:col-span-2">
        <label class="label" for="q">Search</label>
        <input class="input" type="search" id="q" name="q"
               placeholder="Name, phone, reference or email"
               value="<?= $view->e($filters['search'] ?? '') ?>">
    </div>

    <div class="field">
        <label class="label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= $view->e($status->value) ?>"
                    <?= $view->attr(($filters['status'] ?? '') === $status->value, 'selected') ?>>
                    <?= $view->e($status->label()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label class="label" for="doctor_id">Doctor</label>
        <select class="select" id="doctor_id" name="doctor_id">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $id => $name): ?>
                <option value="<?= (int) $id ?>" <?= $view->attr((int) ($filters['doctor_id'] ?? 0) === (int) $id, 'selected') ?>>
                    <?= $view->e($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label class="label" for="date">Date</label>
        <input class="input" type="date" id="date" name="date" value="<?= $view->e($filters['date'] ?? '') ?>">
    </div>

    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
        <button type="submit" class="btn-primary btn-sm">Apply</button>
        <a href="<?= $view->adminUrl('appointments') ?>" class="btn-secondary btn-sm">Clear</a>
        <a href="<?= $view->adminUrl('appointments/day') ?>" class="btn-ghost btn-sm">Day sheet</a>
    </div>
</form>

<div class="card mt-6">
    <?php if ($appointments === []): ?>
        <p class="p-10 text-center text-sm text-slate-500">No appointments match these filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Doctor</th>
                        <th>When</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>"
                                   class="font-mono text-xs font-bold text-medical-700 hover:underline">
                                    <?= $view->e($appointment->reference->value) ?>
                                </a>
                                <?php if ($appointment->isExpress()): ?>
                                    <span class="badge <?= $view->e($appointment->queueTier->badgeClass()) ?> ml-1">Express</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <b class="block text-slate-900"><?= $view->e($appointment->patientName) ?></b>
                                <span class="text-xs text-slate-500"><?= $view->e($appointment->patientPhone->formatNational()) ?></span>
                            </td>
                            <td class="text-slate-600"><?= $view->e($appointment->subjectLabel()) ?></td>
                            <td class="text-slate-600"><?= $view->e($appointment->doctorLabel()) ?></td>
                            <td>
                                <span class="block font-semibold text-slate-800"><?= $view->e($t->dateShort($appointment->date)) ?></span>
                                <span class="text-xs text-slate-500"><?= $view->e($appointment->timeSlot->label()) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $view->e($appointment->status->badgeClass()) ?>">
                                    <?= $view->e($appointment->status->label()) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($appointment->totalAmount->isPositive()): ?>
                                    <span class="badge <?= $view->e($appointment->paymentStatus->badgeClass()) ?>">
                                        <?= $view->e($appointment->paymentStatus->label()) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-bold text-slate-900">
                                <?= $appointment->totalAmount->isPositive() ? $view->e($t->money($appointment->totalAmount)) : '&mdash;' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $view->partial('partials/pagination', [
    'view'       => $view,
    'pagination' => $pagination,
    'basePath'   => $adminPath . '/appointments',
    'query'      => $query,
]) ?>
