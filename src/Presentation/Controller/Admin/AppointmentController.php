<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\DTO\BookingRequest;
use Aster\Application\Service\BookingService;
use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\BookingSource;
use Aster\Domain\Enum\QueueTier;
use Aster\Domain\Enum\TimeSlot;
use Aster\Domain\Exception\BookingException;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\PaymentRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use DateTimeImmutable;

/**
 * Appointment lifecycle management.
 *
 * The listing is scoped by role: a doctor sees only their own queue,
 * regardless of what they put in the query string. That filter is applied
 * server-side after reading the user, so it cannot be removed by editing the
 * URL.
 */
final class AppointmentController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly AppointmentRepository $appointments,
        private readonly PaymentRepository $payments,
        private readonly DoctorRepository $doctors,
        private readonly ServiceRepository $services,
        private readonly PackageRepository $packages,
        private readonly BookingService $booking,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();

        $filters = [
            'status'         => $request->input('status'),
            'payment_status' => $request->input('payment_status'),
            'doctor_id'      => $request->nullableInt('doctor_id'),
            'date'           => $request->input('date'),
            'date_from'      => $request->input('date_from'),
            'date_to'        => $request->input('date_to'),
            'queue_tier'     => $request->input('queue_tier'),
            'source'         => $request->input('source'),
            'search'         => $request->input('q'),
        ];

        // Clinical staff are pinned to their own queue. Overwriting rather
        // than merging means a doctor_id in the query string is ignored.
        if ($user->role->isClinical()) {
            $doctor = $user->doctorId !== null
                ? $this->doctors->findById($user->doctorId)
                : $this->doctors->findByUserId($user->id);

            // A doctor account with no linked doctors row sees nothing, which
            // is the safe failure for a misconfigured account.
            $filters['doctor_id'] = $doctor?->id ?? -1;
        }

        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginate($request, 25);

        $total = $this->appointments->countSearch($filters);

        return $this->renderAdmin('admin/appointments/index', [
            'appointments' => $this->appointments->search(
                $filters,
                $perPage,
                $offset,
                $request->input('sort') ?? 'date_desc',
            ),
            'filters'    => $filters,
            'doctors'    => $this->doctors->options(false),
            'statuses'   => AppointmentStatus::all(),
            'pagination' => $this->paginationMeta($total, $page, $perPage),
            'canEdit'    => $user->can('appointments.write'),
            'meta'       => ['title' => 'Appointments', 'noindex' => true],
        ]);
    }

    public function show(Request $request): Response
    {
        $user        = $this->requireUser();
        $appointment = $this->appointments->findById($request->routeInt('id'));

        if ($appointment === null) {
            throw HttpException::notFound();
        }

        $this->assertVisibleTo($user, $appointment);

        return $this->renderAdmin('admin/appointments/show', [
            'appointment' => $appointment,
            'payments'    => $user->can('payments.view')
                ? $this->payments->forAppointment($appointment->id)
                : [],
            'history'     => $user->can('audit.view')
                ? $this->audit->forTarget('appointment', $appointment->id)
                : [],
            'transitions' => $appointment->status->allowedTransitions(),
            'canEdit'     => $user->can('appointments.write'),
            'meta'        => [
                'title'   => 'Appointment ' . $appointment->reference->value,
                'noindex' => true,
            ],
        ]);
    }

    /** Apply a lifecycle transition. */
    public function updateStatus(Request $request): Response
    {
        $user        = $this->requireUser();
        $id          = $request->routeInt('id');
        $appointment = $this->appointments->findById($id);

        if ($appointment === null) {
            throw HttpException::notFound();
        }

        $this->assertVisibleTo($user, $appointment);

        $target = AppointmentStatus::tryFrom($request->string('status'));

        if ($target === null) {
            return $this->redirectWithError(
                $this->config->adminPath . '/appointments/' . $id,
                'That status is not recognised.',
            );
        }

        // Cancelling without a reason leaves the patient with an email that
        // explains nothing, so it is required here.
        $reason = $request->input('reason');

        if ($target === AppointmentStatus::CANCELLED && ($reason === null || trim($reason) === '')) {
            return $this->redirectWithError(
                $this->config->adminPath . '/appointments/' . $id,
                'Please give a reason for the cancellation - the patient will see it.',
            );
        }

        try {
            $this->booking->changeStatus($id, $target, $user->id, $reason);
        } catch (BookingException $e) {
            return $this->redirectWithError($this->config->adminPath . '/appointments/' . $id, $e->getMessage());
        }

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/appointments/' . $id,
            'Appointment marked as ' . $target->label() . '.',
        );
    }

    /** Manual booking form, for phone and walk-in patients. */
    public function createForm(Request $request): Response
    {
        return $this->renderAdmin('admin/appointments/create', [
            'services' => $this->services->publicList(),
            'packages' => $this->packages->publicList(),
            'doctors'  => $this->doctors->bookableList(),
            'slots'    => TimeSlot::all(),
            'tiers'    => QueueTier::all(),
            'sources'  => [BookingSource::PHONE, BookingSource::WALK_IN, BookingSource::ADMIN],
            'minDate'  => (new DateTimeImmutable('today', $this->config->timezone))->format('Y-m-d'),
            'meta'     => ['title' => 'New Appointment', 'noindex' => true],
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();

        $source = BookingSource::tryFrom($request->string('source', 'admin')) ?? BookingSource::ADMIN;

        try {
            $bookingRequest = BookingRequest::fromRequest(
                $request,
                $this->currentLocale(),
                $source,
                $user->id,
                $this->config->trustProxy(),
            );

            $appointment = $this->booking->book($bookingRequest);
        } catch (ValidationException $e) {
            return $this->redirectWithValidation(
                $this->config->adminPath . '/appointments/create',
                $e,
                $request,
            );
        } catch (BookingException $e) {
            $this->session->flashInput($request->body);

            return $this->redirectWithError($this->config->adminPath . '/appointments/create', $e->getMessage());
        }

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/appointments/' . $appointment->id,
            'Appointment ' . $appointment->reference->value . ' created.',
        );
    }

    /** Update the clinical note on an appointment. */
    public function updateNotes(Request $request): Response
    {
        $user        = $this->requireUser();
        $id          = $request->routeInt('id');
        $appointment = $this->appointments->findById($id);

        if ($appointment === null) {
            throw HttpException::notFound();
        }

        $this->assertVisibleTo($user, $appointment);

        if (!$user->can('patients.notes') && !$user->can('appointments.write')) {
            throw HttpException::forbidden();
        }

        $notes = $request->input('patient_notes');

        $this->appointments->update($id, [
            'patient_notes' => $notes !== null ? mb_substr($notes, 0, 4000) : null,
        ]);

        // recordDiff would put the note's contents in the audit table; a
        // plain record() keeps the trail without duplicating clinical data.
        $this->audit->record(
            AuditLogger::APPOINTMENT_UPDATED,
            'appointment',
            $id,
            'Clinical notes updated',
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/appointments/' . $id,
            'Notes saved.',
        );
    }

    /**
     * Today's schedule across all doctors - the front desk's working view.
     */
    public function daySheet(Request $request): Response
    {
        $user = $this->requireUser();

        $date = $request->input('date')
            ?? (new DateTimeImmutable('today', $this->config->timezone))->format('Y-m-d');

        $filters = ['date' => $date];

        if ($user->role->isClinical()) {
            $doctor               = $this->doctors->findByUserId($user->id);
            $filters['doctor_id'] = $doctor?->id ?? -1;
        }

        return $this->renderAdmin('admin/appointments/day', [
            'appointments' => $this->appointments->search($filters, 200, 0, 'queue'),
            'date'         => new DateTimeImmutable($date),
            'slots'        => TimeSlot::all(),
            'availability' => $this->appointments->aggregateAvailability($date),
            'meta'         => ['title' => 'Day Schedule', 'noindex' => true],
        ]);
    }

    /**
     * Enforce clinical row-level scoping.
     *
     * The list query already filters, but a doctor could still type another
     * appointment's id into the URL. This is the check that stops them.
     */
    private function assertVisibleTo(\Aster\Domain\Entity\User $user, \Aster\Domain\Entity\Appointment $appointment): void
    {
        if (!$user->role->isClinical()) {
            return;
        }

        $doctor = $user->doctorId !== null
            ? $this->doctors->findById($user->doctorId)
            : $this->doctors->findByUserId($user->id);

        if ($doctor === null || $appointment->doctorId !== $doctor->id) {
            throw HttpException::forbidden();
        }
    }
}
