<?php

declare(strict_types=1);

namespace Aster\Domain\DTO;

use Aster\Domain\Enum\BloodGroup;
use Aster\Domain\Enum\Gender;
use Aster\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

/**
 * Registration input for PatientDeduplicationService::findOrRegister()
 * (FRS 8.1). Lives in Domain, not Application/DTO alongside BookingRequest,
 * because a Domain service takes it as a parameter - Domain must not
 * depend on Application (see the dependency-direction rule), so the DTO a
 * Domain service accepts has to live at or below Domain itself.
 *
 * Already validated and normalised by the time it reaches the service:
 * phoneNumber is a PhoneNumber value object (already E.164), not a raw
 * string - MPI-001 ("Accept a validated PatientDTO") is enforced by
 * construction, not re-checked here.
 */
final readonly class PatientDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public DateTimeImmutable $dateOfBirth,
        public Gender $gender,
        public PhoneNumber $phoneNumber,
        public ?string $nationalId = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $emergencyContactName = null,
        public ?string $emergencyContactPhone = null,
        public ?BloodGroup $bloodGroup = null,
        /** Plaintext at this boundary - PatientRepository encrypts on write. @var list<string> */
        public array $allergies = [],
    ) {
    }
}
