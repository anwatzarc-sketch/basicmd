<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\DoctorStatus;
use Aster\Domain\Exception\HttpException;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Storage\FileUploader;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Doctor directory management, including capacity and leave.
 *
 * daily_capacity and slot_capacity are edited here, which makes this screen
 * the control surface for the whole booking engine: raising a doctor's slot
 * capacity immediately widens availability on the public form.
 */
final class DoctorController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly DoctorRepository $doctors,
        private readonly FileUploader $uploader,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        return $this->renderAdmin('admin/doctors/index', [
            'doctors'  => $this->doctors->adminList($request->input('q'), $request->input('status')),
            'filters'  => ['q' => $request->input('q'), 'status' => $request->input('status')],
            'statuses' => DoctorStatus::all(),
            'meta'     => ['title' => 'Doctors', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        $id     = $request->routeInt('id');
        $doctor = $id > 0 ? $this->doctors->findById($id) : null;

        if ($id > 0 && $doctor === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/doctors/form', [
            'doctor'   => $doctor,
            'timeOff'  => $doctor !== null ? $this->doctors->timeOff($doctor->id) : [],
            'statuses' => DoctorStatus::all(),
            'meta'     => [
                'title'   => $doctor === null ? 'Add Doctor' : 'Edit ' . $doctor->fullName,
                'noindex' => true,
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $name = $request->string('full_name');

        if ($name === '' || $request->string('specialty') === '') {
            return $this->redirectWithError(
                $this->config->adminPath . '/doctors' . ($id > 0 ? '/' . $id . '/edit' : '/create'),
                'Name and specialty are required.',
            );
        }

        $data = [
            'full_name'        => $name,
            'full_name_am'     => $request->input('full_name_am'),
            'full_name_om'     => $request->input('full_name_om'),
            'specialty'        => $request->string('specialty'),
            'specialty_am'     => $request->input('specialty_am'),
            'specialty_om'     => $request->input('specialty_om'),
            'experience_years' => max(0, min(70, $request->int('experience_years'))),
            'credentials'      => $request->input('credentials'),
            'bio'              => $request->input('bio'),
            'bio_am'           => $request->input('bio_am'),
            'bio_om'           => $request->input('bio_om'),
            'phone'            => $request->input('phone'),
            'initials'         => mb_strtoupper(mb_substr($request->string('initials'), 0, 4)),
            // Clamped: a capacity of zero silently removes the doctor from
            // every availability query, which looks like a bug to the front
            // desk rather than a setting.
            'daily_capacity'   => max(1, min(100, $request->int('daily_capacity', 16))),
            'slot_capacity'    => max(1, min(50, $request->int('slot_capacity', 4))),
            'consultation_fee' => number_format(max(0, $request->float('consultation_fee')), 2, '.', ''),
            'status'           => (DoctorStatus::tryFrom($request->string('status')) ?? DoctorStatus::ACTIVE)->value,
            'sort_order'       => $request->int('sort_order', 0),
        ];

        $photo = $request->file('photo');

        if ($photo !== null) {
            $stored              = $this->uploader->storeMedia($photo, 'doctors', 800);
            $data['photo_path']  = $stored->relativePath;
        }

        if ($id > 0) {
            $before = $this->doctors->rawRow($id);

            if ($before === null) {
                throw HttpException::notFound();
            }

            // The slug is only regenerated when the name actually changes, so
            // an existing profile URL is not silently broken by an edit to an
            // unrelated field.
            if ((string) $before['full_name'] !== $name) {
                $data['slug'] = $this->doctors->uniqueSlug($name, $id);
            }

            $this->doctors->update($id, $data);

            $this->audit->recordDiff(
                AuditLogger::CONTENT_UPDATED,
                'doctor',
                $id,
                $before,
                $data,
                'Updated doctor ' . $name,
            );

            return $this->redirectWithSuccess($this->config->adminPath . '/doctors', 'Doctor updated.');
        }

        $data['slug'] = $this->doctors->uniqueSlug($name);

        $newId = $this->doctors->create($data);

        $this->audit->record(AuditLogger::CONTENT_CREATED, 'doctor', $newId, 'Added doctor ' . $name);

        return $this->redirectWithSuccess($this->config->adminPath . '/doctors', 'Doctor added.');
    }

    public function delete(Request $request): Response
    {
        $id     = $request->routeInt('id');
        $doctor = $this->doctors->findById($id);

        if ($doctor === null) {
            throw HttpException::notFound();
        }

        // Soft delete only: appointments reference doctors, and the record of
        // who a patient saw must outlive the clinician's employment.
        $this->doctors->softDelete($id);

        $this->audit->record(AuditLogger::CONTENT_DELETED, 'doctor', $id, 'Removed doctor ' . $doctor->fullName);

        return $this->redirectWithSuccess($this->config->adminPath . '/doctors', 'Doctor removed from the directory.');
    }

    public function addTimeOff(Request $request): Response
    {
        $user     = $this->requireUser();
        $doctorId = $request->routeInt('id');

        $from = $request->string('starts_on');
        $to   = $request->string('ends_on');

        if ($from === '' || $to === '' || $to < $from) {
            return $this->redirectWithError(
                $this->config->adminPath . '/doctors/' . $doctorId . '/edit',
                'Please give a valid start and end date.',
            );
        }

        $this->doctors->addTimeOff($doctorId, $from, $to, $request->input('reason'), $user->id);

        $this->audit->record(
            AuditLogger::CONTENT_UPDATED,
            'doctor',
            $doctorId,
            "Added leave {$from} to {$to}",
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/doctors/' . $doctorId . '/edit',
            'Leave recorded. These dates are now closed for booking.',
        );
    }

    public function removeTimeOff(Request $request): Response
    {
        $doctorId = $request->routeInt('id');

        $this->doctors->removeTimeOff($request->routeInt('leaveId'));

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/doctors/' . $doctorId . '/edit',
            'Leave removed.',
        );
    }
}
