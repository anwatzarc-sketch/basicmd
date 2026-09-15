<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Services;

use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\Enum\LedgerCategory;
use Aster\Domain\Enum\LedgerMode;
use Aster\Domain\Enum\ReceivablePaymentMethod;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\UnsettledBalanceException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\Repository\WardLocationRepositoryInterface;
use Aster\Domain\Services\BillingService;
use Aster\Domain\Services\EncounterService;
use Aster\Tests\Support\DatabaseTestCase;

/**
 * FRS 14.2 (ledger invariant) and 14.3 (discharge gate), against the real
 * database.
 */
final class BillingServiceTest extends DatabaseTestCase
{
    private BillingService $billing;
    private LedgerRepositoryInterface $ledger;
    private ReceivablePaymentRepositoryInterface $payments;
    private EncounterRepositoryInterface $encounters;
    private WardLocationRepositoryInterface $wardLocations;

    /** admin@astermedical.et, seeded super_admin - stands in for "an accountant" and "an actor" throughout. */
    private const int ACTOR_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing        = $this->container->get(BillingService::class);
        $this->ledger          = $this->container->get(LedgerRepositoryInterface::class);
        $this->payments        = $this->container->get(ReceivablePaymentRepositoryInterface::class);
        $this->encounters      = $this->container->get(EncounterRepositoryInterface::class);
        $this->wardLocations   = $this->container->get(WardLocationRepositoryInterface::class);
    }

    private function makeEncounter(bool $admitted = false): int
    {
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Billing', 'TestPatient', '1980-01-01', 'other', :phone, UTC_TIMESTAMP())",
            ['pid' => 'PID-6000-' . random_int(10000, 99999), 'phone' => '+2519' . random_int(10000000, 99999999)],
        );
        $patientId = $this->db->lastInsertId();

        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($patientId, VisitType::OPD, null);

        if ($admitted) {
            $this->db->execute(
                "INSERT INTO ward_locations (ward_name, room_number, bed_number, is_transient, is_occupied, daily_rate)
                 VALUES ('Billing Test Ward', :room, 'A', 0, 0, '500.00')",
                ['room' => (string) random_int(100, 999)],
            );
            $bedId = $this->db->lastInsertId();
            $encounterService->upgradeOpdToIpd($encounter->patientVisitNumber, $bedId, 4); // dawit, seeded physician
            $encounter = $this->encounters->findById($encounter->id);
        }

        return $encounter->id;
    }

    private function charge(int $encounterId, string $description, float $amount): int
    {
        return $this->ledger->create([
            'encounter_id'   => $encounterId,
            'accountant_id'  => self::ACTOR_ID,
            'category'       => LedgerCategory::CONSULTATION->value,
            'cost_entry'     => $description,
            'unit'           => 1,
            'per_unit_cost'  => number_format($amount, 2, '.', ''),
            'reason'         => 'Test charge: ' . $description,
            'mode'           => LedgerMode::OPD->value,
        ]);
    }

    private function pay(int $encounterId, float $amount): int
    {
        return $this->payments->create([
            'receipt_id'      => 'RCT-' . date('Ymd') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'encounter_id'    => $encounterId,
            'accountant_id'   => self::ACTOR_ID,
            'amount_paid'     => number_format($amount, 2, '.', ''),
            'payment_method'  => ReceivablePaymentMethod::CASH->value,
        ]);
    }

    // -----------------------------------------------------------------
    //  FRS 14.2 - the ledger invariant
    // -----------------------------------------------------------------

    public function test_balance_equals_ledger_totals_minus_verified_payments_through_a_full_sequence(): void
    {
        $encounterId = $this->makeEncounter();

        self::assertSame(0.0, $this->billing->calculateReceivableBalance($encounterId));

        $this->charge($encounterId, 'Consultation', 300.00);
        self::assertSame(300.0, $this->billing->calculateReceivableBalance($encounterId));

        $this->charge($encounterId, 'Lab work', 450.50);
        self::assertSame(750.5, $this->billing->calculateReceivableBalance($encounterId));

        $this->pay($encounterId, 500.00);
        self::assertSame(250.5, $this->billing->calculateReceivableBalance($encounterId));

        $this->charge($encounterId, 'Follow-up', 100.00);
        self::assertSame(350.5, $this->billing->calculateReceivableBalance($encounterId));

        $this->pay($encounterId, 350.50);
        self::assertSame(0.0, $this->billing->calculateReceivableBalance($encounterId));

        // Historical entries remain present and unmodified throughout.
        self::assertCount(3, $this->ledger->forEncounter($encounterId));
        self::assertCount(2, $this->payments->forEncounter($encounterId));
    }

    public function test_overpayment_produces_a_negative_balance_not_an_error(): void
    {
        $encounterId = $this->makeEncounter();

        $this->charge($encounterId, 'Consultation', 300.00);
        $this->pay($encounterId, 500.00);

        self::assertSame(-200.0, $this->billing->calculateReceivableBalance($encounterId));
        self::assertTrue($this->billing->verifyFinancialClearance($encounterId), 'a credit balance is settled');
    }

    public function test_a_correction_entry_nets_against_the_original_and_both_remain_readable(): void
    {
        $encounterId = $this->makeEncounter();

        $originalId = $this->charge($encounterId, 'Overcharged lab test', 1000.00);
        self::assertSame(1000.0, $this->billing->calculateReceivableBalance($encounterId));

        // Correction: a contra row, NOT an edit to the original.
        $this->ledger->create([
            'encounter_id'    => $encounterId,
            'accountant_id'    => self::ACTOR_ID,
            'category'        => LedgerCategory::LAB->value,
            'cost_entry'      => 'Correction: overcharged lab test',
            'unit'            => 1,
            'per_unit_cost'   => '-1000.00',
            'reason'          => 'Billing error - test was not performed',
            'mode'            => LedgerMode::OPD->value,
            'parent_entry_id' => $originalId,
        ]);

        self::assertSame(0.0, $this->billing->calculateReceivableBalance($encounterId));

        $entries = $this->ledger->forEncounter($encounterId);
        self::assertCount(2, $entries, 'both the original and the correction remain in history');
        self::assertSame(1000.0, $entries[0]->totalCost->toMajor());
        self::assertSame(-1000.0, $entries[1]->totalCost->toMajor());
    }

    // -----------------------------------------------------------------
    //  FRS 14.3 Test A - unpaid balance blocks discharge
    // -----------------------------------------------------------------

    public function test_discharge_with_an_unpaid_balance_is_blocked(): void
    {
        $encounterId = $this->makeEncounter(admitted: true);
        $encounterBefore = $this->encounters->findById($encounterId);
        $bedId = $encounterBefore->currentLocationId;

        $this->charge($encounterId, 'Room fee', 2000.00);

        try {
            $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);
            self::fail('Expected UnsettledBalanceException');
        } catch (UnsettledBalanceException $e) {
            self::assertSame($encounterId, $e->encounterId);
            self::assertTrue($e->balance->isPositive());
        }

        $after = $this->encounters->findById($encounterId);
        self::assertSame(EncounterStatus::ADMITTED, $after->status, 'the encounter must remain active');
        self::assertTrue($this->wardLocations->find($bedId)->isOccupied, 'the bed must remain occupied');
        self::assertCount(1, $this->ledger->forEncounter($encounterId), 'the ledger must remain intact');
    }

    // -----------------------------------------------------------------
    //  FRS 14.3 Test B - a cleared balance discharges successfully
    // -----------------------------------------------------------------

    public function test_discharge_with_a_cleared_balance_succeeds_and_releases_the_bed(): void
    {
        $encounterId = $this->makeEncounter(admitted: true);
        $bedId = $this->encounters->findById($encounterId)->currentLocationId;

        $this->charge($encounterId, 'Room fee', 1500.00);
        $this->pay($encounterId, 1500.00);

        self::assertSame(0.0, $this->billing->calculateReceivableBalance($encounterId));

        $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);

        $after = $this->encounters->findById($encounterId);
        self::assertSame(EncounterStatus::DISCHARGED, $after->status);
        self::assertNotNull($after->dischargedAt);
        self::assertFalse($this->wardLocations->find($bedId)->isOccupied, 'the bed must be released');

        // History intact after discharge.
        self::assertCount(1, $this->ledger->forEncounter($encounterId));
        self::assertCount(1, $this->payments->forEncounter($encounterId));
    }

    // -----------------------------------------------------------------
    //  FRS 14.3 Test C - an authorised override satisfies the gate
    // -----------------------------------------------------------------

    public function test_an_override_allows_discharge_with_an_outstanding_balance(): void
    {
        $encounterId = $this->makeEncounter(admitted: true);
        $bedId = $this->encounters->findById($encounterId)->currentLocationId;

        $this->charge($encounterId, 'Surgical fee', 5000.00);

        // First attempt, no override: rejected.
        try {
            $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);
            self::fail('Expected UnsettledBalanceException before the override');
        } catch (UnsettledBalanceException) {
            // expected
        }

        $this->billing->applyOverride($encounterId, self::ACTOR_ID, 'Charity case - waived by administration');

        self::assertTrue($this->billing->verifyFinancialClearance($encounterId));

        // Second attempt, with the override: succeeds.
        $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);

        $after = $this->encounters->findById($encounterId);
        self::assertSame(EncounterStatus::DISCHARGED, $after->status);
        self::assertFalse($this->wardLocations->find($bedId)->isOccupied);

        // The debt itself is still on record - an override waives the
        // GATE, not the history.
        self::assertSame(5000.0, $this->billing->calculateReceivableBalance($encounterId));
    }

    // -----------------------------------------------------------------
    //  FIN-008 / AC-DIS-06 - a failed discharge must not release the bed
    // -----------------------------------------------------------------

    public function test_a_failed_discharge_does_not_release_the_bed(): void
    {
        $encounterId = $this->makeEncounter(admitted: true);
        $bedId = $this->encounters->findById($encounterId)->currentLocationId;

        $this->charge($encounterId, 'Unpaid charge', 999.99);

        try {
            $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);
        } catch (UnsettledBalanceException) {
            // expected
        }

        self::assertTrue($this->wardLocations->find($bedId)->isOccupied);
    }

    public function test_discharging_an_already_discharged_encounter_is_rejected(): void
    {
        $encounterId = $this->makeEncounter();
        $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);

        $this->expectException(\Aster\Domain\Exception\EncounterException::class);
        $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);
    }

    public function test_an_opd_encounter_can_be_discharged_directly_from_checked_in(): void
    {
        $encounterId = $this->makeEncounter();

        $this->billing->processDischargeOrClosure($encounterId, self::ACTOR_ID);

        self::assertSame(EncounterStatus::DISCHARGED, $this->encounters->findById($encounterId)->status);
    }
}
