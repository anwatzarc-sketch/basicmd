<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\Service\DashboardService;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use DateTimeImmutable;

/**
 * The admin dashboard.
 *
 * What a user sees depends on their role. A doctor gets their own clinical
 * queue and nothing financial; Finance gets settlement figures; the front
 * desk gets the booking pipeline. Rendering one dashboard and hiding panels
 * with CSS would leak the numbers in the HTML source, so the data is never
 * fetched in the first place for a role that should not see it.
 */
final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly DashboardService $dashboard,
        private readonly DoctorRepository $doctors,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $tz   = $this->config->timezone;

        $today = new DateTimeImmutable('today', $tz);

        // Default window: month to date, which is how the clinic reports.
        $from = $request->input('from') ?? $today->modify('first day of this month')->format('Y-m-d');
        $to   = $request->input('to') ?? $today->format('Y-m-d');

        // A doctor sees only their own day.
        if ($user->role->isClinical()) {
            $doctor = $user->doctorId !== null
                ? $this->doctors->findById($user->doctorId)
                : $this->doctors->findByUserId($user->id);

            return $this->renderAdmin('admin/dashboard-doctor', [
                'doctor' => $doctor,
                'queue'  => $doctor !== null
                    ? $this->dashboard->doctorDayQueue($doctor->id, $today->format('Y-m-d'))
                    : [],
                'date'   => $today,
                'meta'   => ['title' => 'My Clinic Day', 'noindex' => true],
            ]);
        }

        $data = [
            'headline'     => $this->dashboard->headline(),
            'attendance'   => $this->dashboard->attendance($from, $to),
            'acquisition'  => $this->dashboard->acquisitionMix($from, $to),
            'doctorLoad'   => $this->dashboard->doctorLoad(),
            'bookingTrend' => $this->dashboard->bookingTrend(30),
            'recent'       => $this->dashboard->recentAppointments(6),
            'inquiries'    => $this->dashboard->recentInquiries(4),
            'topServices'  => $this->dashboard->topServices($from, $to),
            'range'        => ['from' => $from, 'to' => $to],
            'meta'         => ['title' => 'Dashboard', 'noindex' => true],
        ];

        // Financial panels are gated on the permission, not the role, so
        // granting dashboard.finance to a new role needs no change here.
        if ($user->can('dashboard.finance')) {
            $data['financials']   = $this->dashboard->financials($from, $to);
            $data['revenueTrend'] = $this->dashboard->revenueTrend(30);
            $data['express']      = $this->dashboard->expressUptake($from, $to);
            $data['settlement']   = $this->dashboard->settlementByMethod($from, $to);
        }

        // Phase II panels - same rule: gated on the resource permission a
        // role actually holds, not fetched (and so never present in the
        // HTML source) for one that doesn't.
        if ($user->can('patients.view')) {
            $data['mpi'] = $this->dashboard->mpiHeadline($from, $to);
        }

        if ($user->can('encounters.view')) {
            $data['clinical']         = $this->dashboard->clinicalHeadline();
            $data['visitTypeMix']     = $this->dashboard->encountersByVisitType();
            $data['recentEncounters'] = $this->dashboard->recentEncounters(6);
        }

        if ($user->can('billing.view')) {
            $data['ledger'] = $this->dashboard->ledgerSnapshot($from, $to);
        }

        // Super-admin-only: same audit.view permission that gates the
        // full /settings/audit trail, so this panel and that page are
        // never out of sync on who can see them.
        if ($user->can('audit.view')) {
            $data['recentAudit'] = $this->audit->recent(6);
        }

        return $this->renderAdmin('admin/dashboard', $data);
    }
}
