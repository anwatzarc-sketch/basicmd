<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Services;

use Aster\Domain\Enum\EncounterStatus;
use Aster\Domain\Enum\LedgerCategory;
use Aster\Domain\Enum\LedgerMode;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\EncounterException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Services\BillingService;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\QueueService;
use Aster\Tests\Support\DatabaseTestCase;

/** FRS 8.4 / AC-FIN-08: walk-out preserves charges and debt. */
final class QueueServiceTest extends DatabaseTestCase
{
    private QueueService $queue;
    private EncounterRepositoryInterface $encounters;
    private LedgerRepositoryInterface $ledger;
    private BillingService $billing;

    private const int ACTOR_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue        = $this->container->get(QueueService::class);
        $this->encounters   = $this->container->get(EncounterRepositoryInterface::class);
        $this->ledger        = $this->container->get(LedgerRepositoryInterface::class);
        $this->billing       = $this->container->get(BillingService::class);
    }

    private function makePatientId(): int
    {
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Queue', 'TestPatient', '1985-05-05', 'other', :phone, UTC_TIMESTAMP())",
            ['pid' => 'PID-5000-' . random_int(10000, 99999), 'phone' => '+2519' . random_int(10000000, 99999999)],
        );

        return $this->db->lastInsertId();
    }

    public function test_walk_out_from_checked_in_is_left_without_being_seen(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        self::assertSame(EncounterStatus::CHECKED_IN, $encounter->status);

        $this->queue->markWalkOut($encounter->id, 'Waited too long, left.');

        $updated = $this->encounters->findById($encounter->id);
        self::assertSame(EncounterStatus::LEFT_WITHOUT_BEING_SEEN, $updated->status);
    }

    public function test_walk_out_from_in_consultation_is_walk_out(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        $this->db->execute("UPDATE encounters SET status = 'IN_CONSULTATION' WHERE id = :id", ['id' => $encounter->id]);

        $this->queue->markWalkOut($encounter->id, 'Left mid-consultation.');

        $updated = $this->encounters->findById($encounter->id);
        self::assertSame(EncounterStatus::WALK_OUT, $updated->status);
    }

    public function test_a_reason_is_required(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        $this->expectException(EncounterException::class);
        $this->queue->markWalkOut($encounter->id, '   ');
    }

    public function test_walk_out_preserves_existing_charges_and_debt(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        $this->ledger->create([
            'encounter_id'   => $encounter->id,
            'accountant_id'   => self::ACTOR_ID,
            'category'       => LedgerCategory::REGISTRATION->value,
            'cost_entry'     => 'Registration fee',
            'unit'           => 1,
            'per_unit_cost'  => '150.00',
            'reason'         => 'Standard registration fee',
            'mode'           => LedgerMode::OPD->value,
        ]);

        $balanceBefore = $this->billing->calculateReceivableBalance($encounter->id);
        self::assertSame(150.0, $balanceBefore);

        $this->queue->markWalkOut($encounter->id, 'Left before being seen.');

        // AC-FIN-08: walk-out must not erase existing charges or debt.
        self::assertSame($balanceBefore, $this->billing->calculateReceivableBalance($encounter->id));
        self::assertCount(1, $this->ledger->forEncounter($encounter->id));
    }

    public function test_walk_out_on_an_already_terminal_encounter_is_rejected(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        $this->billing->processDischargeOrClosure($encounter->id, self::ACTOR_ID);

        $this->expectException(EncounterException::class);
        $this->queue->markWalkOut($encounter->id, 'Too late, already discharged.');
    }

    public function test_walk_out_writes_an_audit_event(): void
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);
        $encounter = $encounterService->startEncounter($this->makePatientId(), VisitType::OPD, null);

        $this->queue->markWalkOut($encounter->id, 'Patient left.');

        $count = $this->db->fetchInt(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'encounter.walk_out' AND target_id = :id",
            ['id' => $encounter->id],
        );
        self::assertSame(1, $count);
    }
}
