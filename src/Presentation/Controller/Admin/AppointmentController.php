<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\DTO\BookingRequest;
use Aster\Application\Service\BookingService;
use Aster\Domain\DTO\PatientDTO;
use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\BookingSource;
use Aster\Domain\Enum\Gender;
use Aster\Domain\Enum\QueueTier;
use Aster\Domain\Enum\TimeSlot;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\BookingException;
use Aster\Domain\Exception\EncounterException;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\PatientException;
use Aster\Domain\Exception\ValidationException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Domain\ValueObject\PhoneNumber;
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
        private readonly PatientDeduplicationService $patientDedup,
        private readonly EncounterService $encounterService,
        private readonly EncounterRepositoryInterface $encounters,
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
        if ($user->isScopedToOwnQueue()) {
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
            // Phase II check-in bridge - null on every booking made before
            // this shipped, and on any booking never checked in at all.
            'encounter'   => $this->encounters->findByAppointmentId($appointment->id),
            'meta'        => [
                'title'   => 'Appointment ' . $appointment->reference->value,
                'noindex' => true,
            ],
        ]);
    }

    /**
     * Check an existing booking into a clinical encounter (decision 3 of
     * the Phase II plan: the bridge from the unchanged public booking flow
     * to the new Master Patient Index / encounter model).
     *
     * The appointment itself is never written to - this only ever creates
     * a NEW encounters row pointing back at it. Demographic fields the
     * booking form never asked for (date of birth, gender) are collected
     * here, at the one point a real person is standing at the front desk
     * to answer them.
     */
    public function checkIn(Request $request): Response
    {
        $user        = $this->requireUser();
        $id          = $request->routeInt('id');
        $appointment = $this->appointments->findById($id);

        if ($appointment === null) {
            throw HttpException::notFound();
        }

        $this->assertVisibleTo($user, $appointment);

        $formPath = $this->config->adminPath . '/appointments/' . $id;

        if ($appointment->status !== AppointmentStatus::CONFIRMED) {
            return $this->redirectWithError(
                $formPath,
                'Only a confirmed appointment can be checked in.',
            );
        }

        $dateOfBirth = $request->input('date_of_birth');
        $gender      = Gender::tryFrom($request->string('gender'));

        if ($dateOfBirth === null || $dateOfBirth === '' || $gender === null) {
            return $this->redirectWithError(
                $formPath,
                'Date of birth and gender are required to check in.',
            );
        }

        try {
            $dob = new DateTimeImmutable($dateOfBirth);
        } catch (\Exception) {
            return $this->redirectWithError($formPath, 'That date of birth is not valid.');
        }

        // The booking form takes one free-text name field; the MPI wants
        // first/last separately. Split on the first space - imperfect for
        // multi-word given names, but front-desk staff can correct it on
        // the patient record afterwards, and getting this exactly right is
        // not worth a second required field on every public booking.
        [$firstName, $lastName] = $this->splitName($appointment->patientName);

        $dto = new PatientDTO(
            firstName:   $firstName,
            lastName:    $lastName,
            dateOfBirth: $dob,
            gender:      $gender,
            phoneNumber: $appointment->patientPhone,
            email:       $appointment->patientEmail,
        );

        try {
            $patient   = $this->patientDedup->findOrRegister($dto);
            $encounter = $this->encounterService->startEncounter(
                $patient->id,
                VisitType::OPD,
                null,
                $appointment->id,
            );
        } catch (PatientException|EncounterException $e) {
            return $this->redirectWithError($formPath, $e->getMessage());
        }

        $this->audit->record(
            AuditLogger::APPOINTMENT_UPDATED,
            'appointment',
            $appointment->id,
            sprintf('Checked in to encounter %s (patient %s)', $encounter->patientVisitNumber->value, $patient->pid->value),
        );

        return $this->redirectWithSuccess(
            $formPath,
            sprintf('Checked in. Visit number %s.', $encounter->patientVisitNumber->value),
        );
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $fullName): array
    {
        $trimmed = trim($fullName);
        $spaceAt = strpos($trimmed, ' ');

        if ($spaceAt === false) {
            return [$trimmed, ''];
        }

        return [substr($trimmed, 0, $spaceAt), trim(substr($trimmed, $spaceAt + 1))];
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

        if ($user->isScopedToOwnQueue()) {
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
        if (!$user->isScopedToOwnQueue()) {
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
