<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\Service\PatientAuthService;
use Aster\Domain\DTO\PatientDTO;
use Aster\Domain\Enum\BloodGroup;
use Aster\Domain\Enum\Gender;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\PatientException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Domain\ValueObject\PhoneNumber;
use Aster\Infrastructure\Persistence\PatientRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use DateTimeImmutable;

/**
 * Master Patient Index: lookup and registration (FRS 10.1).
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
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $term    = $request->input('q');
        $results = $term !== null && trim($term) !== '' ? $this->patients->search($term) : [];

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

    public function show(Request $request): Response
    {
        $patient = $this->patients->findById($request->routeInt('id'));

        if ($patient === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/patients/show', [
            'patient'   => $patient,
            'allergies' => $this->patients->decryptAllergies($patient),
            'encounters' => $this->encounters->forPatient($patient->id),
            'meta'      => ['title' => $patient->fullName(), 'noindex' => true],
        ]);
    }

    /**
     * Give a patient portal access, or reset it if they already have some
     * (FRS 5.3/10.6). The FRS defines no patient self-registration flow
     * for the portal - only staff can provision it, the same way a staff
     * account itself only ever comes from an administrator invite.
     */
    public function provisionPortalAccess(Request $request): Response
    {
        $id      = $request->routeInt('id');
        $patient = $this->patients->findById($id);

        if ($patient === null) {
            throw HttpException::notFound();
        }

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

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
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
