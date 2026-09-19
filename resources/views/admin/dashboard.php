<?php
/**
 * Admin dashboard.
 *
 * Financial panels render only when the user holds dashboard.finance - the
 * controller does not even fetch those figures otherwise, so they never reach
 * the HTML source of a page a receptionist can view.
 *
 * @var \MediCareMini\Presentation\View\View $view
 * @var \MediCareMini\Domain\Entity\User $user
 * @var array<string,int|string> $headline
 * @var array $attendance
 * @var array $acquisition
 * @var list<array> $doctorLoad
 * @var array<string,int> $bookingTrend
 * @var list<\MediCareMini\Domain\Entity\Appointment> $recent
 * @var array{total_patients:int,new_this_period:int,portal_accounts:int}|null $mpi
 * @var array{active_encounters:int,admitted_today:int,discharged_today:int,beds_occupied:int,beds_total:int,pending_clearance:int}|null $clinical
 * @var array<string,int>|null $visitTypeMix
 * @var list<\MediCareMini\Domain\Entity\Encounter>|null $recentEncounters
 * @var array{charges_posted:\MediCareMini\Domain\ValueObject\Money,payments_posted:\MediCareMini\Domain\ValueObject\Money,outstanding:\MediCareMini\Domain\ValueObject\Money,open_balances:int}|null $ledger
 * @var list<array<string,mixed>>|null $recentAudit
 */

declare(strict_types=1);

$t         = $view->translator;
$adminPath = '/' . $view->config->adminPath;

/** Inline sparkline, drawn as an SVG polyline with soft gradient backdrop area. */
$sparkline = static function (array $series, string $stroke = '#0f8f89'): string {
    $values = array_values($series);
    $count  = count($values);

    if ($count < 2) {
        return '';
    }

    $max = max($values) ?: 1;
    $points = [];
    $areaPoints = ['0,35'];

    foreach ($values as $index => $value) {
        $x = ($index / ($count - 1)) * 100;
        $y = 30 - (($value / $max) * 24) - 2;
        $pt = round($x, 2) . ',' . round($y, 2);
        $points[] = $pt;
        $areaPoints[] = $pt;
    }
    $areaPoints[] = '100,35';

    $gradId = 'grad-' . substr(md5(implode(',', $values) . $stroke), 0, 8);

    return sprintf(
        '<div class="relative h-12 w-full overflow-hidden leading-none">'
        . '<svg viewBox="0 0 100 35" preserveAspectRatio="none" class="h-full w-full" aria-hidden="true">'
        . '<defs>'
        . '<linearGradient id="%s" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%%" stop-color="%s" stop-opacity="0.18"/>'
        . '<stop offset="100%%" stop-color="%s" stop-opacity="0.0"/>'
        . '</linearGradient>'
        . '</defs>'
        . '<polygon fill="url(#%s)" points="%s"/>'
        . '<polyline fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="%s"/>'
        . '</svg>'
        . '</div>',
        $gradId,
        $stroke,
        $stroke,
        $gradId,
        implode(' ', $areaPoints),
        $stroke,
        implode(' ', $points),
    );
};
?>

<!-- ------------------------- Headline metrics ------------------------- -->
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <?php
    $cards = [
        ['Appointments today', $headline['appointments_today'], $headline['new_today'] . ' booked today', 'bg-emerald-50 text-emerald-700 border-emerald-100', $view->adminUrl('appointments')],
        ['Pending confirmation', $headline['pending_appointments'], 'Awaiting front desk', 'bg-amber-50 text-amber-700 border-amber-100', $view->adminUrl('appointments?status=pending')],
        ['Payments to verify', $headline['pending_payments'], 'Finance queue', 'bg-amber-50 text-amber-700 border-amber-100', $view->adminUrl('payments?status=pending')],
        ['Unread enquiries', $headline['unread_inquiries'], 'Needs a reply', 'bg-sky-50 text-sky-700 border-sky-100', $view->adminUrl('inquiries?status=unread')],
    ];
    foreach ($cards as [$label, $value, $sub, $subClass, $url]):
    ?>
        <a href="<?= $url ?>" class="stat-card group relative block overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
            <div class="flex items-center justify-between">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase"><?= $view->e($label) ?></span>
                <span class="h-2 w-2 rounded-full bg-slate-300 transition-colors group-hover:bg-medical-500"></span>
            </div>
            <b class="stat-value mt-3 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $value ?></b>
            <div class="mt-3 flex items-center">
                <span class="inline-flex items-center rounded-lg border px-2.5 py-0.5 text-xs font-semibold <?= $subClass ?>">
                    <?= $view->e($sub) ?>
                </span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- ------------------------- Financials ------------------------- -->
<?php if (isset($financials)): ?>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <?php
        $money = [
            ['Collected', $financials['collected'], 'text-emerald-600', 'Verified payments in range', 'border-emerald-500/20', $view->adminUrl('payments')],
            ['Awaiting verification', $financials['awaiting_verification'], 'text-amber-600', 'Receipts under review', 'border-amber-500/20', $view->adminUrl('payments?status=pending')],
            ['Outstanding', $financials['outstanding'], 'text-rose-600', 'Booked but unpaid', 'border-rose-500/20', $view->adminUrl('billing/ledger?status=outstanding')],
            ['Expected', $financials['expected'], 'text-medical-800', 'Total value booked', 'border-medical-500/20', $view->adminUrl('billing/ledger')],
        ];
        foreach ($money as [$label, $amount, $class, $sub, $borderAccent, $url]):
        ?>
            <a href="<?= $url ?>" class="stat-card relative block overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <div class="absolute inset-x-0 top-0 h-1 border-t-2 <?= $borderAccent ?>"></div>
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase"><?= $view->e($label) ?></span>
                <b class="mt-2 block text-2xl font-black tracking-tight <?= $class ?>"><?= $view->e($t->money($amount)) ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400"><?= $view->e($sub) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ------------------------- Clinical operations & MPI (Phase II) ------------------------- -->
<?php if (isset($clinical) || isset($mpi) || isset($ledger)): ?>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <?php if (isset($clinical)): ?>
            <a href="<?= $view->adminUrl('encounters/workbench') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Active encounters</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $clinical['active_encounters'] ?></b>
                <span class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-medical-600">
                    <span class="h-1.5 w-1.5 rounded-full bg-medical-500 animate-pulse"></span>
                    Currently in the building
                </span>
            </a>
            <a href="<?= $view->adminUrl('encounters') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Admitted / discharged today</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $clinical['admitted_today'] ?> <span class="text-slate-300 font-light">/</span> <?= (int) $clinical['discharged_today'] ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400">Inpatient movement today</span>
            </a>
            <a href="<?= $view->adminUrl('encounters?type=ipd') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Bed occupancy</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $clinical['beds_occupied'] ?><span class="text-slate-400 text-lg font-semibold">/<?= (int) $clinical['beds_total'] ?></span></b>
                <span class="mt-2 block text-xs font-semibold <?= $clinical['beds_total'] > 0 && ($clinical['beds_occupied'] / max(1, $clinical['beds_total'])) >= 0.9 ? 'text-rose-600' : 'text-slate-400' ?>">
                    Non-transient wards &amp; rooms
                </span>
            </a>
            <a href="<?= $view->adminUrl('billing/ledger?pending_clearance=1') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Pending financial clearance</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight <?= $clinical['pending_clearance'] > 0 ? 'text-amber-600' : 'text-slate-900' ?>"><?= (int) $clinical['pending_clearance'] ?></b>
                <span class="mt-2 block text-xs font-semibold text-amber-600">Discharge blocked on balance</span>
            </a>
        <?php endif; ?>

        <?php if (isset($mpi)): ?>
            <a href="<?= $view->adminUrl('patients') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Registered patients</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $mpi['total_patients'] ?></b>
                <span class="mt-2 block text-xs font-semibold text-emerald-600">+<?= (int) $mpi['new_this_period'] ?> new in range</span>
            </a>
            <a href="<?= $view->adminUrl('patients?portal=active') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Portal accounts active</span>
                <b class="stat-value mt-2 block text-3xl font-black tracking-tight text-slate-900"><?= (int) $mpi['portal_accounts'] ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400">
                    <?= $mpi['total_patients'] > 0 ? round(($mpi['portal_accounts'] / $mpi['total_patients']) * 100, 1) : 0 ?>% of patients
                </span>
            </a>
        <?php endif; ?>

        <?php if (isset($ledger)): ?>
            <a href="<?= $view->adminUrl('billing/ledger') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Charged in range</span>
                <b class="mt-2 block text-2xl font-black tracking-tight text-medical-800"><?= $view->e($t->money($ledger['charges_posted'])) ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400">Consumption ledger, this period</span>
            </a>
            <a href="<?= $view->adminUrl('payments') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Collected in range</span>
                <b class="mt-2 block text-2xl font-black tracking-tight text-emerald-600"><?= $view->e($t->money($ledger['payments_posted'])) ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400">Receivable payments, this period</span>
            </a>
            <a href="<?= $view->adminUrl('billing/ledger?status=open') ?>" class="stat-card block rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-medical-500">
                <span class="stat-label text-xs font-bold tracking-wider text-slate-400 uppercase">Outstanding right now</span>
                <b class="mt-2 block text-2xl font-black tracking-tight text-rose-600"><?= $view->e($t->money($ledger['outstanding'])) ?></b>
                <span class="mt-2 block text-xs font-medium text-slate-400"><?= (int) $ledger['open_balances'] ?> open encounter(s) with balance</span>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="mt-6 grid gap-6 xl:grid-cols-3">

    <!-- ------------------------- Trend ------------------------- -->
    <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black tracking-tight text-slate-900">Bookings Trend</h2>
                <p class="text-xs font-medium text-slate-400">Activity monitor over the last 30 days</p>
            </div>
            <a href="<?= $view->adminUrl('appointments') ?>" class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200">
                <?= array_sum($bookingTrend) ?> total
            </a>
        </div>
        <div class="mt-6"><?= $sparkline($bookingTrend, '#0f8f89') ?></div>

        <?php if (isset($revenueTrend)): ?>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-extrabold text-slate-700">Verified revenue, last 30 days</h3>
                    <a href="<?= $view->adminUrl('payments') ?>" class="rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 border border-amber-100 transition-colors hover:bg-amber-100">
                        <?= $view->e($t->money(\MediCareMini\Domain\ValueObject\Money::fromMajor(array_sum($revenueTrend)))) ?>
                    </a>
                </div>
                <div class="mt-4"><?= $sparkline($revenueTrend, '#d99a32') ?></div>
            </div>
        <?php endif; ?>

        <!-- Attendance Metrics -->
        <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3">
            <a href="<?= $view->adminUrl('appointments?status=no_show') ?>" class="group block rounded-xl border border-slate-100 bg-slate-50/60 p-4 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-xs">
                <dt class="stat-label text-xs font-bold uppercase tracking-wider text-slate-400">No-show rate</dt>
                <dd class="mt-1.5 text-2xl font-black tracking-tight <?= $attendance['no_show_rate'] > 15 ? 'text-rose-600' : 'text-emerald-600' ?>">
                    <?= $view->e((string) $attendance['no_show_rate']) ?>%
                </dd>
            </a>
            <a href="<?= $view->adminUrl('appointments?status=completed') ?>" class="group block rounded-xl border border-slate-100 bg-slate-50/60 p-4 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-xs">
                <dt class="stat-label text-xs font-bold uppercase tracking-wider text-slate-400">Completion rate</dt>
                <dd class="mt-1.5 text-2xl font-black tracking-tight text-slate-900"><?= $view->e((string) $attendance['completion_rate']) ?>%</dd>
            </a>
            <a href="<?= $view->adminUrl('appointments?source=web') ?>" class="group block rounded-xl border border-slate-100 bg-slate-50/60 p-4 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-xs">
                <dt class="stat-label text-xs font-bold uppercase tracking-wider text-slate-400">Web share of bookings</dt>
                <dd class="mt-1.5 text-2xl font-black tracking-tight text-medical-700"><?= $view->e((string) $acquisition['web_share']) ?>%</dd>
            </a>
        </dl>
    </div>

    <!-- ------------------------- Doctor load ------------------------- -->
    <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 class="text-base font-black tracking-tight text-slate-900">Today's Doctor Load</h2>
            <a href="<?= $view->adminUrl('doctors') ?>" class="text-xs font-medium text-slate-400 hover:text-medical-600 hover:underline">Capacity &rarr;</a>
        </div>

        <?php if ($doctorLoad === []): ?>
            <div class="flex flex-col items-center justify-center py-10 text-center">
                <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <p class="text-sm font-semibold text-slate-500">No active doctors today</p>
            </div>
        <?php else: ?>
            <ul class="mt-5 grid gap-4">
                <?php foreach ($doctorLoad as $row): ?>
                    <li>
                        <a href="<?= $view->adminUrl('appointments?doctor=' . urlencode((string) ($row['id'] ?? $row['full_name']))) ?>" class="block rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-2xs">
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate font-bold text-slate-800"><?= $view->e($row['full_name']) ?></span>
                                <span class="shrink-0 rounded-md bg-white px-2 py-0.5 text-xs font-extrabold text-slate-600 shadow-2xs border border-slate-200/60">
                                    <?= $row['booked'] ?> / <?= $row['daily_capacity'] ?>
                                </span>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200/60">
                                <div class="h-full rounded-full transition-all duration-500 <?= $row['utilisation'] >= 90 ? 'bg-gradient-to-r from-rose-500 to-rose-600' : ($row['utilisation'] >= 60 ? 'bg-gradient-to-r from-amber-400 to-amber-500' : 'bg-gradient-to-r from-medical-500 to-emerald-500') ?>"
                                     style="width: <?= (float) $row['utilisation'] ?>%"></div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">

    <!-- ------------------------- Recent bookings ------------------------- -->
    <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 class="text-base font-black tracking-tight text-slate-900">Recent Bookings</h2>
            <a href="<?= $view->adminUrl('appointments') ?>" class="inline-flex items-center text-xs font-bold text-medical-600 hover:text-medical-800 hover:underline">
                View all &rarr;
            </a>
        </div>

        <?php if ($recent === []): ?>
            <p class="mt-6 py-8 text-center text-sm font-medium text-slate-400">No recent bookings recorded.</p>
        <?php else: ?>
            <ul class="mt-4 grid gap-3">
                <?php foreach ($recent as $appointment): ?>
                    <li>
                        <a href="<?= $view->adminUrl('appointments/' . $appointment->id) ?>"
                           class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200/60 bg-white p-3.5 shadow-2xs transition-all duration-150 hover:-translate-y-0.5 hover:border-medical-300 hover:shadow-sm">
                            <div class="min-w-0">
                                <b class="block truncate text-sm font-bold text-slate-900 group-hover:text-medical-700"><?= $view->e($appointment->patientName) ?></b>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                    <?= $view->e($appointment->subjectLabel()) ?>
                                    &middot; <?= $view->e($t->dayLabel($appointment->date)) ?>
                                    &middot; <?= $view->e($appointment->timeSlot->label()) ?>
                                </span>
                            </div>
                            <span class="badge shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold tracking-tight shadow-2xs <?= $view->e($appointment->status->badgeClass()) ?>">
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
        <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
            <h2 class="text-base font-black tracking-tight text-slate-900 border-b border-slate-100 pb-4">Quick Actions</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php
                $actions = [
                    ['appointments/create',   'New appointment',    'appointments.write'],
                    ['patients/create',       'Register patient',   'patients.write'],
                    ['encounters/workbench',  'Encounter workbench', 'encounters.view'],
                    ['billing/ledger',        'Post a charge',      'billing.write'],
                    ['doctors/create',        'Add doctor',         'doctors.write'],
                    ['articles/create',       'Write article',      'articles.write'],
                    ['payments',              'Verify payments',    'payments.verify'],
                    ['settings/audit',        'Audit logs',         'audit.view'],
                ];
                foreach ($actions as [$path, $label, $permission]):
                    if (!$user->can($permission)) { continue; }
                ?>
                    <a href="<?= $view->adminUrl($path) ?>"
                       class="group flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50/60 p-3.5 text-left text-sm font-bold text-slate-800 transition-all duration-150 hover:-translate-y-0.5 hover:border-medical-300 hover:bg-medical-50/50 hover:text-medical-900">
                        <span><?= $view->e($label) ?></span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-medical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($inquiries !== []): ?>
            <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-black tracking-tight text-slate-900">Latest Enquiries</h2>
                    <a href="<?= $view->adminUrl('inquiries') ?>" class="text-xs font-bold text-medical-600 hover:text-medical-800 hover:underline">
                        View all &rarr;
                    </a>
                </div>
                <ul class="mt-4 grid gap-3">
                    <?php foreach ($inquiries as $inquiry): ?>
                        <li>
                            <a href="<?= $view->adminUrl('inquiries/' . $inquiry->id) ?>"
                               class="group block rounded-xl border border-slate-200/60 bg-white p-3.5 shadow-2xs transition-all duration-150 hover:border-medical-300 hover:shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <b class="truncate text-sm font-bold text-slate-900 group-hover:text-medical-700"><?= $view->e($inquiry->name) ?></b>
                                    <span class="badge shrink-0 rounded-lg px-2.5 py-0.5 text-xs font-bold <?= $view->e($inquiry->status->badgeClass()) ?>">
                                        <?= $view->e($inquiry->status->label()) ?>
                                    </span>
                                </div>
                                <p class="mt-1.5 truncate text-xs text-slate-500 leading-relaxed"><?= $view->e($inquiry->preview(70)) ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ------------------------- Recent clinical activity (Phase II) ------------------------- -->
<?php if (isset($recentEncounters)): ?>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h2 class="text-base font-black tracking-tight text-slate-900">Recent Clinical Activity</h2>
                <a href="<?= $view->adminUrl('encounters/workbench') ?>" class="text-xs font-bold text-medical-600 hover:underline">
                    Open workbench &rarr;
                </a>
            </div>

            <?php if ($recentEncounters === []): ?>
                <p class="mt-6 py-6 text-center text-sm font-medium text-slate-400">No active encounters right now.</p>
            <?php else: ?>
                <ul class="mt-4 grid gap-3">
                    <?php foreach ($recentEncounters as $encounter): ?>
                        <li>
                            <a href="<?= $view->adminUrl('encounters/' . $encounter->id) ?>"
                               class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200/60 bg-white p-3.5 shadow-2xs transition-all hover:border-medical-300 hover:shadow-sm">
                                <div class="min-w-0">
                                    <b class="block truncate text-sm font-bold text-slate-900 group-hover:text-medical-700"><?= $view->e($encounter->patientName ?? 'Unknown patient') ?></b>
                                    <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">
                                        <?= $view->e($encounter->patientVisitNumber->value) ?>
                                        &middot; <?= $view->e($encounter->visitType->label()) ?>
                                        <?php if ($encounter->locationLabel !== null): ?>
                                            &middot; <?= $view->e($encounter->locationLabel) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="<?= $view->e($encounter->status->chipClass()) ?> shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold">
                                    <?= $view->e($encounter->status->label()) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if (isset($visitTypeMix)): ?>
            <div class="card-pad rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
                <h2 class="text-base font-black tracking-tight text-slate-900 border-b border-slate-100 pb-4">Active Mix by Visit Type</h2>
                <?php if ($visitTypeMix === []): ?>
                    <p class="mt-6 py-6 text-center text-sm font-medium text-slate-400">No active encounters right now.</p>
                <?php else: ?>
                    <?php $mixTotal = max(1, array_sum($visitTypeMix)); ?>
                    <ul class="mt-5 grid gap-4">
                        <?php foreach (['OPD', 'IPD', 'ER'] as $type): ?>
                            <?php $count = $visitTypeMix[$type] ?? 0; ?>
                            <li>
                                <a href="<?= $view->adminUrl('encounters?type=' . strtolower($type)) ?>" class="block rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:shadow-2xs">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-bold text-slate-800"><?= $type ?></span>
                                        <span class="text-xs font-extrabold text-slate-600"><?= $count ?> <span class="text-slate-400 font-medium">(<?= round(($count / $mixTotal) * 100, 1) ?>%)</span></span>
                                    </div>
                                    <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-slate-200/60">
                                        <div class="h-full rounded-full bg-medical-500 transition-all duration-500" style="width: <?= round(($count / $mixTotal) * 100, 1) ?>%"></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ------------------------- Recent audit activity (super_admin only) ------------------------- -->
<?php if (isset($recentAudit)): ?>
    <div class="card-pad mt-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black tracking-tight text-slate-900">Recent Audit Activity</h2>
                <p class="mt-0.5 text-xs font-medium text-slate-400">Who did what across the whole system, most recent first.</p>
            </div>
            <a href="<?= $view->adminUrl('settings/audit') ?>" class="inline-flex items-center text-xs font-bold text-medical-600 hover:text-medical-800 hover:underline">
                Open audit trail &rarr;
            </a>
        </div>

        <?php if ($recentAudit === []): ?>
            <p class="mt-6 py-6 text-center text-sm font-medium text-slate-400">No audit events recorded yet.</p>
        <?php else: ?>
            <div class="table-wrap mt-4 overflow-hidden rounded-xl border border-slate-200/70">
                <table class="table w-full text-left text-sm">
                    <thead class="bg-slate-50/80 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200/70">
                        <tr>
                            <th class="px-4 py-3.5">When</th>
                            <th class="px-4 py-3.5">Who</th>
                            <th class="px-4 py-3.5">Action</th>
                            <th class="px-4 py-3.5">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($recentAudit as $entry): ?>
                            <tr class="cursor-pointer transition-colors hover:bg-slate-50/80" onclick="window.location.href='<?= $view->adminUrl('settings/audit?action=' . urlencode((string) $entry['action'])) ?>'">
                                <td class="whitespace-nowrap px-4 py-3.5 text-xs font-medium text-slate-500">
                                    <?= $view->e($t->dateTime(new DateTimeImmutable($entry['created_at'] . ' UTC'))) ?>
                                </td>
                                <td class="px-4 py-3.5 font-bold text-slate-900">
                                    <?= $view->e($entry['user_name'] ?? $entry['actor_label'] ?? 'System') ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="badge rounded-lg px-2.5 py-1 text-xs font-bold border-slate-200 bg-slate-100 text-slate-600">
                                        <?= $view->e($entry['action']) ?>
                                    </span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3.5 text-xs text-slate-500"><?= $view->e($entry['summary'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ------------------------- Settlement (finance only) ------------------------- -->
<?php if (isset($settlement) && $settlement !== []): ?>
    <div class="card-pad mt-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs">
        <div class="border-b border-slate-100 pb-4">
            <h2 class="text-base font-black tracking-tight text-slate-900">Settlement by Method</h2>
            <p class="mt-0.5 text-xs font-medium text-slate-400">Verified payments only &mdash; reconcile these against your bank statements.</p>
        </div>

        <div class="table-wrap mt-4 overflow-hidden rounded-xl border border-slate-200/70">
            <table class="table w-full text-left text-sm">
                <thead class="bg-slate-50/80 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200/70">
                    <tr>
                        <th class="px-4 py-3.5">Provider</th>
                        <th class="px-4 py-3.5">Channel</th>
                        <th class="px-4 py-3.5 text-right">Payments</th>
                        <th class="px-4 py-3.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($settlement as $row): ?>
                        <tr class="cursor-pointer transition-colors hover:bg-slate-50/80" onclick="window.location.href='<?= $view->adminUrl('payments?provider=' . urlencode((string) $row['provider'])) ?>'">
                            <td class="px-4 py-3.5 font-bold text-slate-900"><?= $view->e($row['provider']) ?></td>
                            <td class="px-4 py-3.5 text-xs font-medium capitalize text-slate-500"><?= $view->e(str_replace('_', ' ', (string) $row['channel'])) ?></td>
                            <td class="px-4 py-3.5 text-right font-semibold text-slate-700"><?= (int) $row['count'] ?></td>
                            <td class="px-4 py-3.5 text-right font-black text-medical-700">
                                <?= $view->e($t->money(\MediCareMini\Domain\ValueObject\Money::fromDatabase($row['total']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>