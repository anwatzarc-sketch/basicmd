<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Controller\Admin;

use MediCareMini\Application\Service\LabReportService;
use MediCareMini\Application\Service\PatientAuthService;
use MediCareMini\Domain\DTO\PatientDTO;
use MediCareMini\Domain\Entity\Patient;
use MediCareMini\Domain\Entity\User;
use MediCareMini\Domain\Enum\BloodGroup;
use MediCareMini\Domain\Enum\ClinicalNoteType;
use MediCareMini\Domain\Enum\DiagnosticCategory;
use MediCareMini\Domain\Enum\DiagnosticStatus;
use MediCareMini\Domain\Enum\Gender;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Domain\Exception\PatientException;
use MediCareMini\Domain\Repository\ClinicalNoteRepositoryInterface;
use MediCareMini\Domain\Repository\DiagnosticOrderRepositoryInterface;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\LedgerRepositoryInterface;
use MediCareMini\Domain\Repository\PrescriptionRepositoryInterface;
use MediCareMini\Domain\Repository\ReceivablePaymentRepositoryInterface;
use MediCareMini\Domain\Services\PatientDeduplicationService;
use MediCareMini\Domain\Services\WardScopeService;
use MediCareMini\Domain\ValueObject\PhoneNumber;
use MediCareMini\Infrastructure\Persistence\AuditLogger;
use MediCareMini\Infrastructure\Persistence\PatientRepository;
use MediCareMini\Infrastructure\Security\SessionManager;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Presentation\Controller\Controller;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;
use MediCareMini\Presentation\Support\PatientDetailAccess;
use MediCareMini\Presentation\View\View;
use DateTimeImmutable;

/**
 * Master Patient Index: lookup, registration, and the canonical Patient
 * Detail shell (FRS 10.1; Patient Aggregate spec §3/§4).
 *
 * show() is the one route/component tree every role reaches - what it
 * renders is entirely a function of PatientDetailAccess, resolved from
 * the signed-in user's real permission set. Each tab's data is fetched
 * ONLY when that tab is both requested AND visible to this user, so a
 * role that cannot see the Financial panel, say, never has that data
 * touch the HTML response at all - the same principle
 * DashboardController's own docblock states for its permission-gated
 * panels.
 *
 * Registration always goes through PatientDeduplicationService -
 * findOrRegister() - never a direct repository insert, so a staff member
 * filling in the form cannot accidentally create a duplicate the same way
 * findOrRegister()'s own three matching levels already prevent one at
 * the check-in bridge.
 */
final class PatientController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly PatientRepository $patients,
        private readonly PatientDeduplicationService $dedup,
        private readonly EncounterRepositoryInterface $encounters,
        private readonly PatientAuthService $portalAuth,
        private readonly ClinicalNoteRepositoryInterface $clinicalNotes,
        private readonly DiagnosticOrderRepositoryInterface $diagnostics,
        private readonly PrescriptionRepositoryInterface $prescriptions,
        private readonly LedgerRepositoryInterface $ledger,
        private readonly ReceivablePaymentRepositoryInterface $receivablePayments,
        private readonly AuditLogger $audit,
        private readonly WardScopeService $wardScope,
        private readonly LabReportService $labReports,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $user    = $this->requireUser();
        $term    = $request->input('q');
        $results = $term !== null && trim($term) !== '' ? $this->patients->search($term) : [];

        // Ward scoping (spec §4.5) applies to the lookup list too - a
        // scoped grant that cannot open an out-of-ward patient must not
        // be able to find them through search either, or the scope would
        // leak through this screen alone. Post-filtered rather than
        // pushed into PatientRepository::search()'s own query, since the
        // check spans encounters/ward_locations, not patients.
        $results = array_values(array_filter(
            $results,
            fn (\MediCareMini\Domain\Entity\Patient $patient): bool => $this->wardScope->isPatientVisible($user, $patient->id),
        ));

        return $this->renderAdmin('admin/patients/index', [
            'term'    => $term ?? '',
            'results' => $results,
            'meta'    => ['title' => 'Patients', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        return $this->renderAdmin('admin/patients/form', [
            'genders'      => Gender::all(),
            'bloodGroups'  => BloodGroup::all(),
            'meta'         => ['title' => 'Register Patient', 'noindex' => true],
        ]);
    }

    public function save(Request $request): Response
    {
        $formPath = $this->config->adminPath . '/patients/create';

        $firstName = $request->string('first_name');
        $lastName  = $request->string('last_name');
        $dobRaw    = $request->input('date_of_birth');
        $gender    = Gender::tryFrom($request->string('gender'));
        $phoneRaw  = $request->input('phone_number');

        if ($firstName === '' || $lastName === '' || $dobRaw === null || $dobRaw === '' || $gender === null) {
            return $this->redirectWithError($formPath, 'First name, last name, date of birth and gender are required.');
        }

        $phone = $phoneRaw !== null ? PhoneNumber::tryFrom($phoneRaw) : null;

        if ($phone === null) {
            return $this->redirectWithError($formPath, 'Please enter a valid phone number.');
        }

        try {
            $dob = new DateTimeImmutable($dobRaw);
        } catch (\Exception) {
            return $this->redirectWithError($formPath, 'That date of birth is not valid.');
        }

        $bloodGroup = BloodGroup::tryFrom($request->string('blood_group'));

        $dto = new PatientDTO(
            firstName:              $firstName,
            lastName:               $lastName,
            dateOfBirth:            $dob,
            gender:                 $gender,
            phoneNumber:            $phone,
            nationalId:             $this->blankToNull($request->input('national_id')),
            email:                  $this->blankToNull($request->input('email')),
            address:                $this->blankToNull($request->input('address')),
            emergencyContactName:   $this->blankToNull($request->input('emergency_contact_name')),
            emergencyContactPhone:  $this->blankToNull($request->input('emergency_contact_phone')),
            bloodGroup:             $bloodGroup,
        );

        try {
            $patient = $this->dedup->findOrRegister($dto);
        } catch (PatientException $e) {
            return $this->redirectWithError($formPath, $e->getMessage());
        }

        // The UI must never imply a new patient was created when the
        // service actually matched an existing one (FRS 10.1's own
        // requirement) - the flash message says which happened.
        $message = $this->wasJustCreated($patient->createdAt)
            ? sprintf('New patient registered: %s (%s).', $patient->fullName(), $patient->pid->value)
            : sprintf('Matched an existing patient record: %s (%s).', $patient->fullName(), $patient->pid->value);

        return $this->redirectWithSuccess($this->config->adminPath . '/patients/' . $patient->id, $message);
    }

    /**
     * The canonical Patient Detail shell. $tab picks which panel renders;
     * an unrecognised or unauthorised tab silently falls back to
     * 'overview' rather than 403ing the whole page - the shell itself is
     * always reachable by anyone holding patients.view (the route's own
     * gate), only individual panels are further restricted.
     */
    public function show(Request $request): Response
    {
        $user    = $this->requireUser();
        $patient = $this->patients->findById($request->routeInt('id'));

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        $tab = $request->input('tab') ?? 'overview';

        if (!PatientDetailAccess::canViewTab($user, $tab)) {
            $tab = 'overview';
        }

        $data = [
            'patient' => $patient,
            'tab'     => $tab,
            'tabs'    => PatientDetailAccess::visibleTabs($user),
            'meta'    => ['title' => $patient->fullName(), 'noindex' => true],
        ];

        $data += match ($tab) {
            'overview'      => $this->overviewData($patient),
            'encounters'    => $this->encountersData($patient),
            'notes'         => $this->notesData($patient),
            'diagnostics'   => $this->diagnosticsData($user, $patient),
            'prescriptions' => $this->prescriptionsData($patient),
            'financial'     => $this->financialData($patient),
            'audit'         => $this->auditData($user, $patient),
            default         => [],
        };

        return $this->renderAdmin('admin/patients/show', $data);
    }

    /** @return array<string, mixed> */
    private function overviewData(Patient $patient): array
    {
        return [
            'allergies'  => $this->patients->decryptAllergies($patient),
            'encounters' => $this->encounters->forPatient($patient->id, 5),
        ];
    }

    /** @return array<string, mixed> */
    private function encountersData(Patient $patient): array
    {
        return ['encounters' => $this->encounters->forPatient($patient->id)];
    }

    /** @return array<string, mixed> */
    private function notesData(Patient $patient): array
    {
        $notes = $this->clinicalNotes->forPatient($patient->id);

        // Decrypted here, in the controller - the one place a note's
        // plaintext is read back, exactly the boundary
        // ClinicalNoteRepositoryInterface::decryptContent() documents.
        // The entity itself never carries plaintext; the view receives a
        // parallel id => plaintext map rather than a mutated entity,
        // since ClinicalNote is readonly by design.
        $noteContents = [];

        foreach ($notes as $note) {
            $noteContents[$note->id] = $this->clinicalNotes->decryptContent($note);
        }

        return [
            'notes'          => $notes,
            'noteContents'   => $noteContents,
            'openEncounters' => $this->encounters->forPatient($patient->id),
            'noteTypes'      => ClinicalNoteType::all(),
        ];
    }

    /** @return array<string, mixed> */
    private function diagnosticsData(User $user, Patient $patient): array
    {
        $scope = PatientDetailAccess::diagnosticCategoryScope($user);
        $orders = $this->diagnostics->forPatient($patient->id, 100, $scope);

        // Same decrypt-at-the-controller boundary as clinical notes -
        // only orders that actually carry a result have anything to
        // decrypt.
        //
        // A Lab order's payload is structured (migration 012), so it is
        // decoded through LabReportService into a LabReport the panel can
        // summarise - parameter counts, how many fell outside range -
        // rather than dumped as the raw JSON its plaintext now is.
        // Imaging and PACS results are still free text and take the
        // original path.
        $orderResults = [];
        $labReports   = [];

        foreach ($orders as $order) {
            if (!$order->hasResults()) {
                continue;
            }

            if ($order->isLab()) {
                $labReports[$order->id] = $this->labReports->saved($order);

                continue;
            }

            $orderResults[$order->id] = $this->diagnostics->decryptResults($order);
        }

        return [
            'orders'         => $orders,
            'orderResults'   => $orderResults,
            'labReports'     => $labReports,
            'openEncounters' => $this->encounters->forPatient($patient->id),
            'categories'     => $scope !== null ? [$scope] : DiagnosticCategory::all(),
        ];
    }

    /** @return array<string, mixed> */
    private function prescriptionsData(Patient $patient): array
    {
        return [
            'prescriptions'  => $this->prescriptions->forPatient($patient->id),
            'openEncounters' => $this->encounters->forPatient($patient->id),
        ];
    }

    /** @return array<string, mixed> */
    private function financialData(Patient $patient): array
    {
        $charges = $this->ledger->sumForPatient($patient->id);
        $paid    = $this->receivablePayments->sumForPatient($patient->id);

        return [
            'charges'     => $charges,
            'paid'        => $paid,
            'outstanding' => $charges->subtract($paid),
            'ledger'      => $this->ledger->forPatient($patient->id, 25),
            'payments'    => $this->receivablePayments->forPatient($patient->id, 25),
        ];
    }

    /** @return array<string, mixed> */
    private function auditData(User $user, Patient $patient): array
    {
        $ownOnly = PatientDetailAccess::auditScopedToOwnEntriesOnly($user);

        return [
            'auditEntries'  => $this->audit->forPatient($patient->id, 50, $ownOnly ? $user->id : null),
            'auditScopedToOwn' => $ownOnly,
        ];
    }

    /**
     * Edit demographics/contact/emergency contact (spec §4.3, Receptionist's
     * [Edit] action - gated on patients.write, the same permission that
     * already covers registration, not a new one: this is the same "the
     * front desk owns this patient's record data" right, just applied to
     * an existing row instead of a new one.
     */
    public function update(Request $request): Response
    {
        $user    = $this->requireUser();
        $id      = $request->routeInt('id');
        $patient = $this->patients->findById($id);
        $back    = $this->config->adminPath . '/patients/' . $id;

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        $firstName = $request->string('first_name');
        $lastName  = $request->string('last_name');
        $phoneRaw  = $request->input('phone_number');

        if ($firstName === '' || $lastName === '') {
            return $this->redirectWithError($back, 'First and last name are required.');
        }

        $phone = $phoneRaw !== null ? PhoneNumber::tryFrom($phoneRaw) : null;

        if ($phone === null) {
            return $this->redirectWithError($back, 'Please enter a valid phone number.');
        }

        $this->patients->update($id, [
            'first_name'              => $firstName,
            'last_name'               => $lastName,
            'phone_number'            => $phone->e164,
            'email'                   => $this->blankToNull($request->input('email')),
            'address'                 => $this->blankToNull($request->input('address')),
            'emergency_contact_name'  => $this->blankToNull($request->input('emergency_contact_name')),
            'emergency_contact_phone' => $this->blankToNull($request->input('emergency_contact_phone')),
        ]);

        $this->audit->record(AuditLogger::CONTENT_UPDATED, 'patient', $id, 'Updated demographics/contact details');

        return $this->redirectWithSuccess($back, 'Patient details updated.');
    }

    /**
     * Give a patient portal access, or reset it if they already have some
     * (FRS 5.3/10.6). The FRS defines no patient self-registration flow
     * for the portal - only staff can provision it, the same way a staff
     * account itself only ever comes from an administrator invite.
     */
    public function provisionPortalAccess(Request $request): Response
    {
        $user    = $this->requireUser();
        $id      = $request->routeInt('id');
        $patient = $this->patients->findById($id);

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        $temporary = $this->portalAuth->provisionAccess($patient->id);

        // Shown once, on screen only - see UserController's identical
        // temporary-credential handling for why this is never logged or
        // emailed.
        $this->session->flash(
            'success',
            sprintf(
                'Portal access ready for %s. Patient ID: %s - Temporary password: %s. Share both securely.',
                $patient->fullName(),
                $patient->pid->value,
                $temporary,
            ),
        );

        return $this->redirectToAdmin('patients/' . $patient->id);
    }

    /**
     * Create a clinical note (spec §4.1/§4.2). note_type is validated
     * against PatientDetailAccess::allowedNoteTypes() - the server-side
     * enforcement of the nurse's vitals/triage-only restriction, never
     * trusted from the submitted form alone.
     */
    public function postClinicalNote(Request $request): Response
    {
        $user      = $this->requireUser();
        $patientId = $request->routeInt('id');
        $patient   = $this->patients->findById($patientId);
        $back      = $this->config->adminPath . '/patients/' . $patientId . '?tab=notes';

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        $encounterId = $request->nullableInt('encounter_id');
        $noteType    = ClinicalNoteType::tryFrom($request->string('note_type'));
        $content     = trim($request->string('content'));

        if ($encounterId === null || $noteType === null || $content === '') {
            return $this->redirectWithError($back, 'An encounter, a note type, and content are all required.');
        }

        if (!PatientDetailAccess::canWriteNoteType($user, $noteType)) {
            return $this->redirectWithError($back, 'You are not authorised to record this type of note.');
        }

        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null || $encounter->patientId !== $patient->id) {
            return $this->redirectWithError($back, 'That encounter does not belong to this patient.');
        }

        $data = [
            'encounter_id'      => $encounter->id,
            'author_id'         => $user->id,
            'note_type'         => $noteType->value,
            'content_encrypted' => $this->clinicalNotes->encryptContent($content),
            'icd_code'          => $this->blankToNull($request->input('icd_code')),
        ];

        if ($noteType === ClinicalNoteType::VITALS) {
            $vitals = array_filter([
                'bp_systolic'      => $request->nullableInt('bp_systolic'),
                'bp_diastolic'     => $request->nullableInt('bp_diastolic'),
                'heart_rate'       => $request->nullableInt('heart_rate'),
                'temperature_c'    => $this->blankToNull($request->input('temperature_c')),
                'respiratory_rate' => $request->nullableInt('respiratory_rate'),
                'spo2'             => $request->nullableInt('spo2'),
            ], static fn ($v): bool => $v !== null);

            if ($vitals !== []) {
                $data['vitals'] = $vitals;
            }
        }

        $this->clinicalNotes->create($data);

        $this->audit->record(
            AuditLogger::CLINICAL_NOTE_CREATED,
            'encounter',
            $encounter->id,
            sprintf('%s note recorded for %s', $noteType->label(), $encounter->patientVisitNumber->value),
        );

        return $this->redirectWithSuccess($back, 'Clinical note recorded.');
    }

    /** Place a diagnostic order (spec §4.1: Physician only). */
    public function postDiagnosticOrder(Request $request): Response
    {
        $user      = $this->requireUser();
        $patientId = $request->routeInt('id');
        $patient   = $this->patients->findById($patientId);
        $back      = $this->config->adminPath . '/patients/' . $patientId . '?tab=diagnostics';

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        if (!PatientDetailAccess::canOrderDiagnostics($user)) {
            throw HttpException::forbidden();
        }

        $encounterId = $request->nullableInt('encounter_id');
        $category    = DiagnosticCategory::tryFrom($request->string('category'));
        $testCode    = trim($request->string('test_code'));
        $testName    = trim($request->string('test_name'));
        $icdCode     = trim($request->string('icd_code'));

        if ($encounterId === null || $category === null || $testCode === '' || $testName === '' || $icdCode === '') {
            return $this->redirectWithError($back, 'An encounter, category, test code, test name and ICD code are all required.');
        }

        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null || $encounter->patientId !== $patient->id) {
            return $this->redirectWithError($back, 'That encounter does not belong to this patient.');
        }

        $orderId = $this->diagnostics->create([
            'encounter_id'          => $encounter->id,
            'ordering_physician_id' => $user->id,
            'category'              => $category->value,
            'test_code'             => $testCode,
            'test_name'             => $testName,
            'icd_code'              => $icdCode,
            'status'                => DiagnosticStatus::ORDERED->value,
        ]);

        $this->audit->record(
            AuditLogger::DIAGNOSTIC_ORDER_CREATED,
            'encounter',
            $encounter->id,
            sprintf('%s order placed: %s (%s)', $category->value, $testName, $encounter->patientVisitNumber->value),
        );

        return $this->redirectWithSuccess($back, sprintf('%s order #%d placed.', $category->value, $orderId));
    }

    /**
     * Advance a diagnostic order's status and/or record its result.
     * Gated per-order by its OWN category via PatientDetailAccess::
     * canRecordResult() - a Lab Technician may act on a Lab order
     * regardless of who is signed in next to them acting on an Imaging
     * one, because the check is against the row just loaded, never
     * against a category the caller merely claims.
     */
    public function postDiagnosticResult(Request $request): Response
    {
        $user      = $this->requireUser();
        $patientId = $request->routeInt('id');
        $patient   = $this->patients->findById($patientId);
        $back      = $this->config->adminPath . '/patients/' . $patientId . '?tab=diagnostics';

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        $order = $this->diagnostics->findById($request->routeInt('orderId'));

        if ($order === null) {
            throw HttpException::notFound();
        }

        $encounter = $this->encounters->findById($order->encounterId);

        if ($encounter === null || $encounter->patientId !== $patient->id) {
            throw HttpException::notFound();
        }

        if (!PatientDetailAccess::canRecordResult($user, $order->category)) {
            throw HttpException::forbidden();
        }

        $status = DiagnosticStatus::tryFrom($request->string('status'));

        if ($status === null || !$order->status->canTransitionTo($status)) {
            return $this->redirectWithError($back, sprintf('Cannot move a %s order to %s.', $order->status->label(), $request->string('status')));
        }

        $data = ['status' => $status->value];
        $resultText = trim($request->string('results'));

        if ($status === DiagnosticStatus::COMPLETED && $resultText !== '') {
            $data['results_payload_encrypted'] = $this->diagnostics->encryptResults($resultText);
        }

        $this->diagnostics->updateProgress($order->id, $data);

        $this->audit->record(
            AuditLogger::DIAGNOSTIC_RESULT_RECORDED,
            'encounter',
            $encounter->id,
            sprintf('Order #%d (%s) moved to %s', $order->id, $order->testName, $status->label()),
        );

        return $this->redirectWithSuccess($back, 'Order updated.');
    }

    /** Write a prescription (spec §4.1: Physician only). */
    public function postPrescription(Request $request): Response
    {
        $user      = $this->requireUser();
        $patientId = $request->routeInt('id');
        $patient   = $this->patients->findById($patientId);
        $back      = $this->config->adminPath . '/patients/' . $patientId . '?tab=prescriptions';

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        if (!PatientDetailAccess::canWritePrescriptions($user)) {
            throw HttpException::forbidden();
        }

        $encounterId = $request->nullableInt('encounter_id');
        $icdCode     = trim($request->string('icd_code'));
        $medication  = trim($request->string('medication_name'));
        $dosage      = trim($request->string('dosage'));
        $frequency   = trim($request->string('frequency'));
        $duration    = $request->int('duration_days');

        if ($encounterId === null || $icdCode === '' || $medication === '' || $dosage === '' || $frequency === '' || $duration < 1) {
            return $this->redirectWithError($back, 'An encounter, ICD code, medication, dosage, frequency and a duration of at least one day are all required.');
        }

        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null || $encounter->patientId !== $patient->id) {
            return $this->redirectWithError($back, 'That encounter does not belong to this patient.');
        }

        $this->prescriptions->create([
            'encounter_id'    => $encounter->id,
            'prescriber_id'   => $user->id,
            'icd_code'        => $icdCode,
            'medication_name' => $medication,
            'dosage'          => $dosage,
            'frequency'       => $frequency,
            'duration_days'   => $duration,
        ]);

        $this->audit->record(
            AuditLogger::PRESCRIPTION_CREATED,
            'encounter',
            $encounter->id,
            sprintf('Prescribed %s (%s) for %s', $medication, $dosage, $encounter->patientVisitNumber->value),
        );

        return $this->redirectWithSuccess($back, 'Prescription recorded.');
    }

    /**
     * Flip the bare is_dispensed toggle - spec §6's option (a): no
     * dispensed_by/at columns exist, so there is nothing more to record
     * than the boolean itself.
     */
    public function postDispense(Request $request): Response
    {
        $user      = $this->requireUser();
        $patientId = $request->routeInt('id');
        $patient   = $this->patients->findById($patientId);
        $back      = $this->config->adminPath . '/patients/' . $patientId . '?tab=prescriptions';

        if ($patient === null) {
            throw HttpException::notFound();
        }

        $this->assertWardVisible($user, $patient->id);

        if (!PatientDetailAccess::canDispense($user)) {
            throw HttpException::forbidden();
        }

        $prescription = $this->prescriptions->findById($request->routeInt('rxId'));

        if ($prescription === null) {
            throw HttpException::notFound();
        }

        $encounter = $this->encounters->findById($prescription->encounterId);

        if ($encounter === null || $encounter->patientId !== $patient->id) {
            throw HttpException::notFound();
        }

        $this->prescriptions->markDispensed($prescription->id);

        $this->audit->record(
            AuditLogger::PRESCRIPTION_DISPENSED,
            'encounter',
            $encounter->id,
            sprintf('Dispensed %s for %s', $prescription->medicationName, $encounter->patientVisitNumber->value),
        );

        return $this->redirectWithSuccess($back, 'Marked as dispensed.');
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    /**
     * Ward-scoped access (spec §4.5) - the one extra check every entry
     * point onto a specific patient carries alongside its own can()
     * gate. Denied the same way AppointmentController::assertVisibleTo()
     * denies its own-queue scoping (403, not a third response shape for
     * what is, from the requester's point of view, the same "you can't
     * see this patient" outcome as a 404).
     */
    private function assertWardVisible(User $user, int $patientId): void
    {
        if (!$this->wardScope->isPatientVisible($user, $patientId)) {
            throw HttpException::forbidden();
        }
    }

    /**
     * A row created in the last few seconds is treated as "just now" for
     * the flash message's wording - findOrRegister() does not itself
     * report whether it matched or registered, so this infers it from
     * created_at rather than widening that service's return type just for
     * a phrasing choice in one screen.
     */
    private function wasJustCreated(DateTimeImmutable $createdAt): bool
    {
        return $createdAt->getTimestamp() >= (time() - 5);
    }
}
