<?php
/**
 * Admin dashboard.
 *
 * Financial panels render only when the user holds dashboard.finance - the
 * controller does not even fetch those figures otherwise, so they never reach
 * the HTML source of a page a receptionist can view.
 *
 * @var \Aster\Presentation\View\View $view
 * @var \Aster\Domain\Entity\User $user
 * @var array<string,int|string> $headline
 * @var array $attendance
 * @var array $acquisition
 * @var list<array> $doctorLoad
 * @var array<string,int> $bookingTrend
 * @var list<\Aster\Domain\Entity\Appointment> $recent
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = $view->config->adminPath;

/** Inline sparkline, drawn as an SVG polyline - no charting library. */
$sparkline = static function (array $series, string $stroke = '#0f8f89'): string {
    $values = array_values($series);
    $count  = count($values);

    if ($count < 2) {
        return '';
    }

    $max = max($values) ?: 1;
    $points = [];

    foreach ($values as $index => $value) {
        $x = ($index / ($count - 1)) * 100;
        $y = 30 - (($value / $max) * 28);
        $points[] = round($x, 2) . ',' . round($y, 2);
    }

    return sprintf(
        '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="h-10 w-full" aria-hidden="true">'
        . '<polyline fill="none" stroke="%s" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" points="%s"/>'
        . '</svg>',
        $stroke,
        implode(' ', $points),
    );
};
?>

<!-- ------------------------- Headline metrics ------------------------- -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php
    $cards = [
        ['Appointments today', $headline['appointments_today'], $headline['new_today'] . ' booked today', 'text-emerald-600'],
        ['Pending confirmation', $headline['pending_appointments'], 'Awaiting front desk', 'text-amber-600'],
        ['Payments to verify', $headline['pending_payments'], 'Finance queue', 'text-amber-600'],
        ['Unread enquiries', $headline['unread_inquiries'], 'Needs a reply', 'text-sky-600'],
    ];
    foreach ($cards as [$label, $value, $sub, $subClass]):
    ?>
        <div class="stat-card">
            <span class="stat-label"><?= $view->e($label) ?></span>
            <b class="stat-value"><?= (int) $value ?></b>
            <span class="mt-1 block text-xs font-semibold <?= $subClass ?>"><?= $view->e($sub) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- ------------------------- Financials ------------------------- -->
<?php if (isset($financials)): ?>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $money = [
            ['Collected', $financials['collected'], 'text-emerald-700', 'Verified payments in range'],
            ['Awaiting verification', $financials['awaiting_verification'], 'text-amber-700', 'Receipts under review'],
            ['Outstanding', $financials['outstanding'], 'text-rose-700', 'Booked but unpaid'],
            ['Expected', $financials['expected'], 'text-medical-800', 'Total value booked'],
        ];
        foreach ($money as [$label, $amount, $class, $sub]):
        ?>
            <div class="stat-card">
                <span class="stat-label"><?= $view->e($label) ?></span>
                <b class="mt-2 block text-2xl font-extrabold <?= $class ?>"><?= $view->e($t->money($amount)) ?></b>
                <span class="mt-1 block text-xs text-slate-400"><?= $view->e($sub) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mt-6 grid gap-6 xl:grid-cols-3">

    <!-- ------------------------- Trend ------------------------- -->
    <div class="card-pad xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-extrabold text-medical-900">Bookings, last 30 days</h2>
            <span class="text-xs font-bold text-slate-400"><?= array_sum($bookingTrend) ?> total</span>
        </div>
        <div class="mt-4"><?= $sparkline($bookingTrend) ?></div>

        <?php if (isset($revenueTrend)): ?>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-extrabold text-slate-700">Verified revenue, last 30 days</h3>
                    <span class="text-xs font-bold text-slate-400">
                        <?= $view->e($t->money(\Aster\Domain\ValueObject\Money::fromMajor(array_sum($revenueTrend)))) ?>
                    </span>
                </div>
                <div class="mt-3"><?= $sparkline($revenueTrend, '#d99a32') ?></div>
            </div>
        <?php endif; ?>

        <!-- Attendance: the no-show rate is the number the reminder engine
             exists to move, so it leads. -->
        <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3">
            <div>
                <dt class="stat-label">No-show rate</dt>
                <dd class="mt-1 text-2xl font-extrabold <?= $attendance['no_show_rate'] > 15 ? 'text-rose-600' : 'text-emerald-600' ?>">
                    <?= $view->e((string) $attendance['no_show_rate']) ?>%
                </dd>
            </div>
            <div>
                <dt class="stat-label">Completion rate</dt>
                <dd class="mt-1 text-2xl font-extrabold text-medical-800"><?= $view->e((string) $attendance['completion_rate']) ?>%</dd>
            </div>
            <div>
                <dt class="stat-label">Web share of bookings</dt>
                <dd class="mt-1 text-2xl font-extrabold text-medical-800"><?= $view->e((string) $acquisition['web_share']) ?>%</dd>
            </div>
        </dl>
    </div>

    <!-- ------------------------- Doctor load ------------------------- -->
    <div class="card-pad">
        <h2 class="text-base font-extrabold text-medical-900">Today's load</h2>

        <?php if ($doctorLoad === []): ?>
            <p class="mt-4 text-sm text-slate-500">No active doctors.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-4">
                <?php foreach ($doctorLoad as $row): ?>
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="truncate font-semibold text-slate-700"><?= $view->e($row['full_name']) ?></span>
                            <span class="shrink-0 text-xs font-bold text-slate-500">
                                <?= $row['booked'] ?>/<?= $row['daily_capacity'] ?>
                            </span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full <?= $row['utilisation'] >= 90 ? 'bg-rose-500' : ($row['utilisation'] >= 60 ? 'bg-amber-500' : 'bg-medical-500') ?>"
                                 style="width: <?= (float) $row['utilisation'] ?>%"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">

    <!-- ------------------------- Recent bookings ------------------------- -->
    <div class="card-pad">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-extrabold text-medical-900">Recent bookings</h2>
            <a href="<?= $view->adminUrl('appointments') ?>" class="text-xs font-bold text-medical-600 hover:underline">
                View all
            </a>
        </div>

        <?php if ($recent === []): ?>
            <p class="mt-4 text-sm text-slate-500">No bookings yet.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-2.5">
                <?php foreach ($recent as $appointment): ?>
                    <li>
                        <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>"
                           class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 p-3.5 transition hover:border-medical-300">
                            <div class="min-w-0">
                                <b class="block truncate text-sm text-slate-900"><?= $view->e($appointment->patientName) ?></b>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                    <?= $view->e($appointment->subjectLabel()) ?>
                                    &middot; <?= $view->e($t->dayLabel($appointment->date)) ?>
                                    &middot; <?= $view->e($appointment->timeSlot->label()) ?>
                                </span>
                            </div>
                            <span class="badge shrink-0 <?= $view->e($appointment->status->badgeClass()) ?>">
                                <?= $view->e($appointment->status->label()) ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- ------------------------- Quick actions + enquiries ------------------------- -->
    <div class="grid gap-6">
        <div class="card-pad">
            <h2 class="text-base font-extrabold text-medical-900">Quick actions</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php
                $actions = [
                    ['appointments/create', 'New appointment', 'appointments.write'],
                    ['doctors/create',      'Add doctor',      'doctors.write'],
                    ['articles/create',     'Write article',   'articles.write'],
                    ['payments',            'Verify payments', 'payments.verify'],
                ];
                foreach ($actions as [$path, $label, $permission]):
                    if (!$user->can($permission)) { continue; }
                ?>
                    <a href="<?= $view->adminUrl($path) ?>"
                       class="rounded-xl bg-medical-50 p-4 text-left text-sm font-bold text-medical-800 transition hover:bg-medical-100">
                        <?= $view->e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($inquiries !== []): ?>
            <div class="card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-extrabold text-medical-900">Latest enquiries</h2>
                    <a href="<?= $view->adminUrl('inquiries') ?>" class="text-xs font-bold text-medical-600 hover:underline">
                        View all
                    </a>
                </div>
                <ul class="mt-4 grid gap-2.5">
                    <?php foreach ($inquiries as $inquiry): ?>
                        <li>
                            <a href="<?= $view->adminUrl('inquiries/' . $inquiry->id) ?>"
                               class="block rounded-xl border border-slate-100 p-3.5 transition hover:border-medical-300">
                                <div class="flex items-center justify-between gap-3">
                                    <b class="truncate text-sm text-slate-900"><?= $view->e($inquiry->name) ?></b>
                                    <span class="badge shrink-0 <?= $view->e($inquiry->status->badgeClass()) ?>">
                                        <?= $view->e($inquiry->status->label()) ?>
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500"><?= $view->e($inquiry->preview(70)) ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ------------------------- Settlement (finance only) ------------------------- -->
<?php if (isset($settlement) && $settlement !== []): ?>
    <div class="card-pad mt-6">
        <h2 class="text-base font-extrabold text-medical-900">Settlement by method</h2>
        <p class="mt-1 text-xs text-slate-500">Verified payments only - reconcile these against your bank statements.</p>

        <div class="table-wrap mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Channel</th>
                        <th class="text-right">Payments</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settlement as $row): ?>
                        <tr>
                            <td class="font-semibold text-slate-900"><?= $view->e($row['provider']) ?></td>
                            <td class="text-slate-500"><?= $view->e(str_replace('_', ' ', (string) $row['channel'])) ?></td>
                            <td class="text-right"><?= (int) $row['count'] ?></td>
                            <td class="text-right font-bold text-medical-700">
                                <?= $view->e($t->money(\Aster\Domain\ValueObject\Money::fromDatabase($row['total']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
