<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Feature\Services;

use MediCareMini\Domain\DTO\PatientDTO;
use MediCareMini\Domain\Enum\Gender;
use MediCareMini\Domain\Exception\PatientException;
use MediCareMini\Domain\Services\PatientDeduplicationService;
use MediCareMini\Domain\ValueObject\PhoneNumber;
use MediCareMini\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;

/**
 * FRS AC-MPI-01 through AC-MPI-06, exercised against the real database -
 * the matching queries and the advisory lock are exactly the kind of
 * behaviour a mock cannot verify.
 */
final class PatientDeduplicationServiceTest extends DatabaseTestCase
{
    private PatientDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->container->get(PatientDeduplicationService::class);
    }

    private function dto(array $overrides = []): PatientDTO
    {
        $defaults = [
            'firstName'   => 'Selam',
            'lastName'    => 'Bekele',
            'dateOfBirth' => new DateTimeImmutable('1990-05-14'),
            'gender'      => Gender::FEMALE,
            'phoneNumber' => PhoneNumber::fromString('0911' . random_int(100000, 999999)),
            'nationalId'  => null,
        ];

        $merged = [...$defaults, ...$overrides];

        return new PatientDTO(...$merged);
    }

    // -----------------------------------------------------------------
    //  AC-MPI-04: a genuinely new patient gets one unique PID.
    // -----------------------------------------------------------------

    public function test_registers_a_new_patient_with_a_well_formed_pid(): void
    {
        $patient = $this->service->findOrRegister($this->dto());

        $year = (int) (new DateTimeImmutable())->format('Y');
        self::assertSame($year, (int) substr($patient->pid->value, 4, 4));
        self::assertMatchesRegularExpression('/^PID-\d{4}-\d{5}$/', $patient->pid->value);
        self::assertSame('Selam', $patient->firstName);
    }

    public function test_two_different_patients_get_two_different_pids(): void
    {
        $a = $this->service->findOrRegister($this->dto(['firstName' => 'Aisha']));
        $b = $this->service->findOrRegister($this->dto(['firstName' => 'Bethel']));

        self::assertNotSame($a->pid->value, $b->pid->value);
        self::assertNotSame($a->id, $b->id);
    }

    public function test_registering_a_new_patient_writes_an_audit_row(): void
    {
        $patient = $this->service->findOrRegister($this->dto(['firstName' => 'Auditable']));

        $row = $this->db->fetchOne(
            "SELECT action, target_type, target_id FROM audit_logs
             WHERE action = 'patient.registered' AND target_type = 'patient' AND target_id = :id",
            ['id' => $patient->id],
        );

        self::assertNotNull($row, 'patient registration must write a patient.registered audit row');
    }

    public function test_returning_an_existing_matched_patient_writes_no_additional_audit_row(): void
    {
        $phone = PhoneNumber::fromString('0955' . random_int(100000, 999999));

        $first = $this->service->findOrRegister($this->dto(['firstName' => 'Matched', 'phoneNumber' => $phone]));
        $this->service->findOrRegister($this->dto([
            'firstName' => 'Different Name On File', 'lastName' => 'But Same Phone', 'phoneNumber' => $phone,
        ]));

        $count = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'patient.registered' AND target_type = 'patient' AND target_id = :id",
            ['id' => $first->id],
        );

        self::assertSame(1, $count, 'a matched-existing lookup must not write a second registration audit row');
    }

    // -----------------------------------------------------------------
    //  AC-MPI-01: exact national_id match returns the existing patient.
    // -----------------------------------------------------------------

    public function test_exact_national_id_match_returns_the_existing_patient_not_a_new_one(): void
    {
        $nationalId = 'ET-' . bin2hex(random_bytes(6));

        $first = $this->service->findOrRegister($this->dto(['nationalId' => $nationalId]));

        // Different name, different DOB, different phone - only the
        // national_id matches, and that alone must be decisive per FRS 8.1
        // Level 1.
        $second = $this->service->findOrRegister($this->dto([
            'firstName'   => 'Someone Else Entirely',
            'lastName'    => 'Different Surname',
            'dateOfBirth' => new DateTimeImmutable('1975-01-01'),
            'nationalId'  => $nationalId,
        ]));

        self::assertSame($first->id, $second->id);
        self::assertSame('Selam', $second->firstName, 'must return the EXISTING record, not overwrite it');
    }

    // -----------------------------------------------------------------
    //  AC-MPI-02: exact phone match returns the existing patient.
    // -----------------------------------------------------------------

    public function test_exact_phone_match_returns_the_existing_patient(): void
    {
        $phone = PhoneNumber::fromString('0922' . random_int(100000, 999999));

        $first  = $this->service->findOrRegister($this->dto(['phoneNumber' => $phone]));
        $second = $this->service->findOrRegister($this->dto([
            'firstName'   => 'A Totally Different Name',
            'lastName'    => 'Also Different',
            'dateOfBirth' => new DateTimeImmutable('1960-12-25'),
            'phoneNumber' => $phone,
        ]));

        self::assertSame($first->id, $second->id);
    }

    // -----------------------------------------------------------------
    //  AC-MPI-03: exact name + DOB match returns the existing patient.
    // -----------------------------------------------------------------

    public function test_exact_demographic_match_returns_the_existing_patient(): void
    {
        $dob = new DateTimeImmutable('1988-03-03');

        $first  = $this->service->findOrRegister($this->dto([
            'firstName' => 'Demographic', 'lastName' => 'Match', 'dateOfBirth' => $dob,
        ]));
        $second = $this->service->findOrRegister($this->dto([
            'firstName' => 'Demographic', 'lastName' => 'Match', 'dateOfBirth' => $dob,
            // Different phone - name+DOB alone must be decisive per Level 2.
            'phoneNumber' => PhoneNumber::fromString('0933' . random_int(100000, 999999)),
        ]));

        self::assertSame($first->id, $second->id);
    }

    public function test_a_different_date_of_birth_is_not_a_demographic_match(): void
    {
        $first  = $this->service->findOrRegister($this->dto([
            'firstName' => 'Same', 'lastName' => 'Name', 'dateOfBirth' => new DateTimeImmutable('1990-01-01'),
        ]));
        $second = $this->service->findOrRegister($this->dto([
            'firstName' => 'Same', 'lastName' => 'Name', 'dateOfBirth' => new DateTimeImmutable('1991-01-01'),
        ]));

        self::assertNotSame($first->id, $second->id);
    }

    // -----------------------------------------------------------------
    //  National id takes priority over a coincidentally different phone
    //  or demographic profile (Level 1 before Level 2 - the FRS's stated
    //  order, not merely "any match").
    // -----------------------------------------------------------------

    public function test_national_id_match_is_checked_before_demographic_match(): void
    {
        $nationalId = 'ET-PRIORITY-' . bin2hex(random_bytes(4));

        $original = $this->service->findOrRegister($this->dto([
            'firstName' => 'Original', 'lastName' => 'Person', 'nationalId' => $nationalId,
        ]));

        // Completely different demographics, SAME national id.
        $again = $this->service->findOrRegister($this->dto([
            'firstName' => 'Completely', 'lastName' => 'Unrelated',
            'dateOfBirth' => new DateTimeImmutable('2000-06-06'),
            'nationalId' => $nationalId,
        ]));

        self::assertSame($original->id, $again->id);
    }

    // -----------------------------------------------------------------
    //  Ambiguous matches must not be silently merged (the FRS leaves this
    //  case undefined; the implementation plan requires an explicit
    //  refusal rather than a guess).
    // -----------------------------------------------------------------

    public function test_two_existing_patients_sharing_a_phone_raises_ambiguous_match(): void
    {
        $sharedPhone = PhoneNumber::fromString('0944' . random_int(100000, 999999));

        // Seed two DISTINCT patients who happen to share a household phone -
        // inserted directly, bypassing dedup, to simulate data that already
        // existed before this feature.
        $this->service->findOrRegister($this->dto([
            'firstName' => 'Household', 'lastName' => 'MemberOne',
            'dateOfBirth' => new DateTimeImmutable('1970-01-01'),
            'phoneNumber' => $sharedPhone,
        ]));
        $this->db->execute(
            "UPDATE patients SET phone_number = :phone
             WHERE first_name = 'Household' AND last_name = 'MemberOne'",
            ['phone' => $sharedPhone->e164],
        );

        // A second, genuinely different patient, ALSO on that phone number -
        // create directly to avoid the dedup service itself deduping it.
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Household', 'MemberTwo', '1972-02-02', 'male', :phone, UTC_TIMESTAMP())",
            ['pid' => 'PID-9999-00001', 'phone' => $sharedPhone->e164],
        );

        $this->expectException(PatientException::class);

        // A third submission on that same phone cannot know which of the
        // two it means.
        $this->service->findOrRegister($this->dto([
            'firstName' => 'Ambiguous', 'lastName' => 'Submission', 'phoneNumber' => $sharedPhone,
        ]));
    }

    // Concurrency (MPI-005/MPI-007) is covered by
    // PatientDeduplicationConcurrencyTest, a SEPARATE test class that never
    // opens a database connection in the parent process before spawning
    // workers - see that class's docblock for why sharing this one's
    // DatabaseTestCase parent would silently break the race it exists to
    // prove.
}
