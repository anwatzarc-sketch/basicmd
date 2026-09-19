<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Feature\Services;

use MediCareMini\Domain\Enum\EncounterStatus;
use MediCareMini\Domain\Enum\VisitType;
use MediCareMini\Domain\Exception\EncounterException;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\WardLocationRepositoryInterface;
use MediCareMini\Domain\Services\EncounterService;
use MediCareMini\Domain\ValueObject\VisitNumber;
use MediCareMini\Tests\Support\DatabaseTestCase;

/**
 * FRS AC-ENC-01 through AC-ENC-10, exercised against the real database.
 * The concurrency invariant (AC-ENC-07/08, "two concurrent upgrades to the
 * same bed cannot both succeed") is covered separately in
 * BedAllocationConcurrencyTest - real subprocesses, real row locks.
 */
final class EncounterServiceTest extends DatabaseTestCase
{
    private EncounterService $service;
    private WardLocationRepositoryInterface $wardLocations;

    /** An existing seeded physician - id 4, dawit@medicaremini.radiants.net.et. */
    private const int PHYSICIAN_ID = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service       = $this->container->get(EncounterService::class);
        $this->wardLocations = $this->container->get(WardLocationRepositoryInterface::class);
    }

    private function makePatient(string $firstName = 'Test'): int
    {
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, :fn, 'Patient', '1990-01-01', 'other', :phone, UTC_TIMESTAMP())",
            [
                'pid'   => 'PID-9000-' . random_int(10000, 99999),
                'fn'    => $firstName,
                'phone' => '+2519' . random_int(10000000, 99999999),
            ],
        );

        return $this->db->lastInsertId();
    }

    private function makeLocation(bool $transient = false, bool $occupied = false): int
    {
        $this->db->execute(
            "INSERT INTO ward_locations (ward_name, room_number, bed_number, is_transient, is_occupied, daily_rate)
             VALUES ('Test Ward', :room, :bed, :transient, :occupied, '500.00')",
            [
                'room'      => (string) random_int(100, 999),
                'bed'       => 'A',
                'transient' => $transient ? 1 : 0,
                'occupied'  => $occupied ? 1 : 0,
            ],
        );

        return $this->db->lastInsertId();
    }

    // -----------------------------------------------------------------
    //  startEncounter() - AC-ENC-01, 02, 03
    // -----------------------------------------------------------------

    public function test_starting_an_encounter_creates_it_checked_in_with_a_well_formed_visit_number(): void
    {
        $patientId = $this->makePatient();

        $encounter = $this->service->startEncounter($patientId, VisitType::OPD, null);

        self::assertMatchesRegularExpression('/^VIS-\d{8}-\d{5}$/', $encounter->patientVisitNumber->value);
        self::assertSame(EncounterStatus::CHECKED_IN, $encounter->status);
        self::assertSame(VisitType::OPD, $encounter->visitType);
        self::assertSame($patientId, $encounter->patientId);
        self::assertNull($encounter->currentLocationId);
    }

    public function test_starting_an_encounter_for_a_nonexistent_patient_is_rejected(): void
    {
        $this->expectException(EncounterException::class);
        $this->service->startEncounter(999999999, VisitType::OPD, null);
    }

    public function test_starting_an_encounter_at_a_transient_location_does_not_change_occupancy(): void
    {
        $patientId  = $this->makePatient();
        $locationId = $this->makeLocation(transient: true);

        $encounter = $this->service->startEncounter($patientId, VisitType::OPD, $locationId);

        self::assertSame($locationId, $encounter->currentLocationId);

        $location = $this->wardLocations->find($locationId);
        self::assertFalse($location->isOccupied, 'a transient location must never be marked occupied');
    }

    public function test_starting_an_encounter_at_an_available_admission_location_occupies_it(): void
    {
        $patientId  = $this->makePatient();
        $locationId = $this->makeLocation(transient: false, occupied: false);

        $this->service->startEncounter($patientId, VisitType::ER, $locationId);

        self::assertTrue($this->wardLocations->find($locationId)->isOccupied);
    }

    public function test_starting_an_encounter_at_an_already_occupied_location_is_rejected(): void
    {
        $patientId  = $this->makePatient();
        $locationId = $this->makeLocation(transient: false, occupied: true);

        $this->expectException(EncounterException::class);
        $this->service->startEncounter($patientId, VisitType::ER, $locationId);
    }

    public function test_starting_an_encounter_writes_an_audit_event(): void
    {
        $patientId = $this->makePatient();
        $encounter = $this->service->startEncounter($patientId, VisitType::OPD, null);

        $count = $this->db->fetchInt(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'encounter.started' AND target_id = :id",
            ['id' => $encounter->id],
        );
        self::assertSame(1, $count);
    }

    // -----------------------------------------------------------------
    //  upgradeOpdToIpd() - AC-ENC-04 through AC-ENC-10
    // -----------------------------------------------------------------

    public function test_upgrading_a_checked_in_encounter_admits_it_and_preserves_the_visit_number(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation(transient: false, occupied: false);

        $started = $this->service->startEncounter($patientId, VisitType::OPD, null);
        $originalVisitNumber = $started->patientVisitNumber->value;

        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, self::PHYSICIAN_ID);

        $encounters = $this->container->get(EncounterRepositoryInterface::class);
        $updated    = $encounters->findByVisitNumber(VisitNumber::fromString($originalVisitNumber));

        self::assertNotNull($updated);
        self::assertSame($originalVisitNumber, $updated->patientVisitNumber->value, 'ENC-001: visit number must never change');
        self::assertSame(EncounterStatus::ADMITTED, $updated->status);
        self::assertSame(VisitType::IPD, $updated->visitType);
        self::assertSame($bedId, $updated->currentLocationId);
        self::assertSame(self::PHYSICIAN_ID, $updated->primaryPhysicianId);
        self::assertNotNull($updated->admittedAt);
    }

    public function test_upgrading_occupies_the_target_bed(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation(transient: false, occupied: false);
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, self::PHYSICIAN_ID);

        self::assertTrue($this->wardLocations->find($bedId)->isOccupied);
    }

    public function test_upgrading_a_discharged_encounter_is_rejected(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation();
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        $this->db->execute(
            "UPDATE encounters SET status = 'DISCHARGED' WHERE id = :id",
            ['id' => $started->id],
        );

        $this->expectException(EncounterException::class);
        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, self::PHYSICIAN_ID);
    }

    public function test_upgrading_to_an_already_occupied_bed_is_rejected_and_bed_stays_with_its_original_occupant(): void
    {
        $patientA = $this->makePatient('PatientA');
        $patientB = $this->makePatient('PatientB');
        $bedId    = $this->makeLocation(transient: false, occupied: false);

        $encounterA = $this->service->startEncounter($patientA, VisitType::OPD, null);
        $this->service->upgradeOpdToIpd($encounterA->patientVisitNumber, $bedId, self::PHYSICIAN_ID);

        $encounterB = $this->service->startEncounter($patientB, VisitType::OPD, null);

        $this->expectException(EncounterException::class);

        try {
            $this->service->upgradeOpdToIpd($encounterB->patientVisitNumber, $bedId, self::PHYSICIAN_ID);
        } finally {
            // The bed must still show patient A's admission, not be
            // vacated or reassigned by the failed attempt (AC-ENC-08: a
            // failed upgrade must not leave an inconsistent state).
            $encounters = $this->container->get(EncounterRepositoryInterface::class);
            $stillA     = $encounters->findByVisitNumber($encounterA->patientVisitNumber);
            self::assertSame(EncounterStatus::ADMITTED, $stillA->status);
            self::assertSame($bedId, $stillA->currentLocationId);
        }
    }

    public function test_upgrading_to_a_transient_location_is_rejected(): void
    {
        $patientId = $this->makePatient();
        $transient = $this->makeLocation(transient: true);
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        $this->expectException(EncounterException::class);
        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $transient, self::PHYSICIAN_ID);
    }

    public function test_upgrading_with_a_non_physician_user_is_rejected(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation();
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        // User id 2 is reception@medicaremini.radiants.net.et (receptionist), not a physician.
        $this->expectException(EncounterException::class);
        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, 2);
    }

    public function test_upgrading_a_nonexistent_visit_number_is_rejected(): void
    {
        $bedId = $this->makeLocation();

        $this->expectException(EncounterException::class);
        $this->service->upgradeOpdToIpd(
            VisitNumber::fromString('VIS-20260101-99999'),
            $bedId,
            self::PHYSICIAN_ID,
        );
    }

    public function test_upgrading_writes_an_audit_event(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation();
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, self::PHYSICIAN_ID);

        $count = $this->db->fetchInt(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'encounter.admitted_ipd' AND target_id = :id",
            ['id' => $started->id],
        );
        self::assertSame(1, $count);
    }

    public function test_a_failed_upgrade_leaves_the_bed_unoccupied(): void
    {
        $patientId = $this->makePatient();
        $bedId     = $this->makeLocation();
        $started   = $this->service->startEncounter($patientId, VisitType::OPD, null);

        // Force rejection via an invalid physician, AFTER the bed lookup
        // would otherwise have succeeded, to prove the whole operation
        // rolls back together rather than partially applying.
        try {
            $this->service->upgradeOpdToIpd($started->patientVisitNumber, $bedId, 2);
        } catch (EncounterException) {
            // expected
        }

        self::assertFalse($this->wardLocations->find($bedId)->isOccupied, 'FIN-008-equivalent: a failed admission must not occupy the bed');
    }

    // -----------------------------------------------------------------
    //  Check-in bridge (decision 3): encounters.appointment_id links back
    //  to the UNCHANGED public booking flow, optionally.
    // -----------------------------------------------------------------

    private function makeAppointment(): int
    {
        $this->db->execute(
            "INSERT INTO appointments
                (booking_ref, patient_name, patient_phone, service_id, appointment_date, time_slot, status, payment_status)
             VALUES (:ref, 'Bridge Test Patient', :phone, 1, :date, '08:00-10:00', 'confirmed', 'unpaid')",
            [
                'ref'   => 'AMC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                'phone' => '+2519' . random_int(10000000, 99999999),
                'date'  => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            ],
        );

        return $this->db->lastInsertId();
    }

    public function test_starting_an_encounter_with_an_appointment_id_links_it(): void
    {
        $patientId     = $this->makePatient();
        $appointmentId = $this->makeAppointment();

        $encounter = $this->service->startEncounter($patientId, VisitType::OPD, null, $appointmentId);

        self::assertSame($appointmentId, $encounter->appointmentId);
    }

    public function test_starting_an_encounter_without_an_appointment_id_is_a_walk_in(): void
    {
        $patientId = $this->makePatient();

        $encounter = $this->service->startEncounter($patientId, VisitType::OPD, null);

        self::assertNull($encounter->appointmentId);
    }

    public function test_checking_in_the_same_appointment_twice_is_rejected(): void
    {
        $patientId     = $this->makePatient();
        $appointmentId = $this->makeAppointment();

        $this->service->startEncounter($patientId, VisitType::OPD, null, $appointmentId);

        $this->expectException(EncounterException::class);
        $this->service->startEncounter($patientId, VisitType::OPD, null, $appointmentId);
    }

    public function test_checking_in_does_not_modify_the_appointment_row_itself(): void
    {
        $patientId     = $this->makePatient();
        $appointmentId = $this->makeAppointment();

        $before = $this->db->fetchOne('SELECT * FROM appointments WHERE id = :id', ['id' => $appointmentId]);
        $this->service->startEncounter($patientId, VisitType::OPD, null, $appointmentId);
        $after = $this->db->fetchOne('SELECT * FROM appointments WHERE id = :id', ['id' => $appointmentId]);

        self::assertSame($before, $after, 'the check-in bridge must never write to the appointments table');
    }
}
