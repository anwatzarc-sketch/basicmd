<?php

declare(strict_types=1);

namespace Aster\Domain\Services;

use Aster\Domain\DTO\PatientDTO;
use Aster\Domain\Entity\Patient;
use Aster\Domain\Exception\PatientException;
use Aster\Domain\Repository\AdvisoryLockInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\Repository\PatientRepositoryInterface;
use Aster\Domain\ValueObject\PatientId;
use DateTimeImmutable;
use RuntimeException;

/**
 * The Master Patient Index's find-or-register workflow (FRS 8.1).
 *
 * Matching order, exactly as specified:
 *   Level 1 - exact national_id, OR exact phone_number
 *   Level 2 - exact first_name + last_name + date_of_birth
 *   else    - register a new patient with a freshly issued PID
 *
 * The entire method runs inside one advisory lock (MPI-007: "Handle
 * concurrent registration safely"). A single global key rather than one
 * scoped to, say, the phone number is deliberate: matching happens across
 * THREE different fields (national_id, phone, name+DOB), and two
 * concurrent registrations could collide on any one of them even when
 * their phone numbers differ (e.g. the same national_id submitted under
 * two different contact numbers by mistake) - only a lock that serialises
 * the whole check-then-create sequence closes every one of those windows
 * at once. Patient registration is a low-frequency, front-desk operation,
 * so serialising it globally costs nothing observable while removing the
 * entire class of race this method exists to prevent.
 */
final readonly class PatientDeduplicationService
{
    private const string LOCK_KEY = 'mpi:registration';
    private const int LOCK_TIMEOUT_SECONDS = 5;

    public function __construct(
        private PatientRepositoryInterface $patients,
        private NumberSequenceInterface $sequence,
        private AdvisoryLockInterface $lock,
    ) {
    }

    public function findOrRegister(PatientDTO $dto): Patient
    {
        return $this->lock->withLock(
            self::LOCK_KEY,
            fn (): Patient => $this->findOrRegisterLocked($dto),
            self::LOCK_TIMEOUT_SECONDS,
        );
    }

    private function findOrRegisterLocked(PatientDTO $dto): Patient
    {
        // --- Level 1: exact identifier matching ------------------------

        if ($dto->nationalId !== null && $dto->nationalId !== '') {
            $match = $this->patients->findByNationalId($dto->nationalId);

            if ($match !== null) {
                return $match;
            }
        }

        $phoneMatches = $this->patients->findAllByPhone($dto->phoneNumber->e164);

        if (count($phoneMatches) === 1) {
            return $phoneMatches[0];
        }

        if (count($phoneMatches) > 1) {
            throw PatientException::ambiguousMatch('phone number');
        }

        // --- Level 2: exact demographic matching ------------------------

        $demographicMatches = $this->patients->findAllByDemographics(
            $dto->firstName,
            $dto->lastName,
            $dto->dateOfBirth,
        );

        if (count($demographicMatches) === 1) {
            return $demographicMatches[0];
        }

        if (count($demographicMatches) > 1) {
            throw PatientException::ambiguousMatch('name and date of birth');
        }

        // --- No match: register a genuinely new patient -----------------

        return $this->register($dto);
    }

    private function register(PatientDTO $dto): Patient
    {
        $year = (int) (new DateTimeImmutable())->format('Y');
        $pid  = PatientId::forYear($year, $this->sequence->next('pid:' . $year));

        $data = [
            'pid'                      => $pid->value,
            'national_id'              => $dto->nationalId,
            'first_name'               => $dto->firstName,
            'last_name'                => $dto->lastName,
            'date_of_birth'            => $dto->dateOfBirth->format('Y-m-d'),
            'gender'                   => $dto->gender->value,
            'phone_number'             => $dto->phoneNumber->e164,
            'email'                    => $dto->email,
            'address'                  => $dto->address,
            'emergency_contact_name'   => $dto->emergencyContactName,
            'emergency_contact_phone'  => $dto->emergencyContactPhone,
            'blood_group'              => $dto->bloodGroup?->value,
        ];

        if ($dto->allergies !== []) {
            $data['allergies_encrypted'] = $this->patients->encryptAllergies($dto->allergies);
        }

        $id = $this->patients->create($data);

        $created = $this->patients->findById($id);

        if ($created === null) {
            // Mirrors BookingService's own guard after createWithCapacityCheck:
            // an insert reporting success with no row to read back means
            // something is badly wrong with the connection, not a business
            // rule - fail loudly rather than return null and push the crash
            // one caller further away from its cause.
            throw new RuntimeException('Patient vanished immediately after creation.');
        }

        return $created;
    }
}
