<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Feature\Services;

use MediCareMini\Domain\Enum\VisitType;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\WardLocationRepositoryInterface;
use MediCareMini\Domain\Services\EncounterService;
use MediCareMini\Domain\Enum\EncounterStatus;
use MediCareMini\Infrastructure\Container\Bootstrap;
use MediCareMini\Infrastructure\Persistence\Database;
use PHPUnit\Framework\TestCase;

/**
 * FRS 14.1: two simultaneous OPD-to-IPD upgrades targeting the SAME bed
 * must not both succeed.
 *
 * Does NOT extend DatabaseTestCase, for a different (and more fundamental)
 * reason than PatientDeduplicationConcurrencyTest's docblock explains for
 * that class: DatabaseTestCase wraps a test in an UNCOMMITTED transaction
 * on the TEST PROCESS's own connection. The spawned worker subprocesses
 * open their OWN, separate connections - an uncommitted row is invisible
 * to any connection other than the one that wrote it, so the workers
 * would find no patient, no encounter and no bed to race over at all.
 * Fixture setup here therefore uses a real, committed connection (and
 * cleans up explicitly afterwards, in a finally block, rather than
 * relying on a rollback that would never apply to this data regardless).
 */
final class BedAllocationConcurrencyTest extends TestCase
{
    private const int PHYSICIAN_ID = 4; // dawit@medicaremini.radiants.net.et, seeded

    public function test_two_concurrent_upgrades_to_the_same_bed_exactly_one_succeeds(): void
    {
        $basePath  = dirname(__DIR__, 3);
        $container = Bootstrap::boot($basePath, cli: true);
        $db        = $container->get(Database::class);

        $stamp = bin2hex(random_bytes(4));

        // --- Fixtures: two patients, two OPD encounters, one bed --------
        $patientAId = $this->createPatient($db, 'RaceA' . $stamp);
        $patientBId = $this->createPatient($db, 'RaceB' . $stamp);
        $bedId      = $this->createBed($db, $stamp);

        /** @var EncounterService $encounterService */
        $encounterService = $container->get(EncounterService::class);
        $encounterA = $encounterService->startEncounter($patientAId, VisitType::OPD, null);
        $encounterB = $encounterService->startEncounter($patientBId, VisitType::OPD, null);

        self::assertSame(EncounterStatus::CHECKED_IN, $encounterA->status);
        self::assertSame(EncounterStatus::CHECKED_IN, $encounterB->status);

        try {
            // --- Race: both encounters, both targeting the SAME bed -----
            $workerScript = __DIR__ . '/../Support/bed_allocation_race_worker.php';
            $outputDir    = sys_get_temp_dir() . '/medicaremini_bed_race_' . $stamp;
            mkdir($outputDir);

            $outA = $outputDir . '/worker_a.txt';
            $outB = $outputDir . '/worker_b.txt';

            $procA = proc_open(
                ['php', $workerScript, $encounterA->patientVisitNumber->value, (string) $bedId, (string) self::PHYSICIAN_ID, $outA],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipesA,
            );
            $procB = proc_open(
                ['php', $workerScript, $encounterB->patientVisitNumber->value, (string) $bedId, (string) self::PHYSICIAN_ID, $outB],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipesB,
            );

            fclose($pipesA[1]);
            fclose($pipesA[2]);
            fclose($pipesB[1]);
            fclose($pipesB[2]);

            $codeA = proc_close($procA);
            $codeB = proc_close($procB);

            self::assertSame(0, $codeA, 'worker A crashed (a REJECTION is expected output, not a crash)');
            self::assertSame(0, $codeB, 'worker B crashed (a REJECTION is expected output, not a crash)');

            self::assertFileExists($outA);
            self::assertFileExists($outB);

            $resultA = trim((string) file_get_contents($outA));
            $resultB = trim((string) file_get_contents($outB));

            // --- Exactly one succeeded, the other was cleanly rejected ---
            $results = [$resultA, $resultB];
            $oks      = array_filter($results, static fn (string $r): bool => $r === 'OK');
            $rejected = array_filter($results, static fn (string $r): bool => str_starts_with($r, 'REJECTED:'));

            self::assertCount(1, $oks, "expected exactly one success, got: A={$resultA} B={$resultB}");
            self::assertCount(1, $rejected, "expected exactly one rejection, got: A={$resultA} B={$resultB}");
            self::assertSame(
                'REJECTED:location_occupied',
                reset($rejected),
                'the loser must be rejected specifically because the bed was already taken',
            );

            // --- Final state: bed occupied, winner ADMITTED, loser still CHECKED_IN ---
            /** @var WardLocationRepositoryInterface $wardLocations */
            $wardLocations = $container->get(WardLocationRepositoryInterface::class);
            $bed = $wardLocations->find($bedId);
            self::assertTrue($bed->isOccupied, 'the bed must end up occupied - exactly once, not zero, not twice');

            /** @var EncounterRepositoryInterface $encounters */
            $encounters = $container->get(EncounterRepositoryInterface::class);
            $finalA = $encounters->findByVisitNumber($encounterA->patientVisitNumber);
            $finalB = $encounters->findByVisitNumber($encounterB->patientVisitNumber);

            $admittedCount = (int) ($finalA->status === EncounterStatus::ADMITTED)
                + (int) ($finalB->status === EncounterStatus::ADMITTED);
            $checkedInCount = (int) ($finalA->status === EncounterStatus::CHECKED_IN)
                + (int) ($finalB->status === EncounterStatus::CHECKED_IN);

            self::assertSame(1, $admittedCount, 'exactly one encounter must have been admitted');
            self::assertSame(1, $checkedInCount, 'the loser must remain CHECKED_IN, not partially mutated');

            array_map('unlink', [$outA, $outB]);
            rmdir($outputDir);
        } finally {
            // Cleanup - real committed rows across three tables, not
            // covered by any transaction rollback (see this class's
            // docblock for why).
            $db->execute('DELETE FROM encounters WHERE id IN (:a, :b)', ['a' => $encounterA->id, 'b' => $encounterB->id]);
            $db->execute('DELETE FROM ward_locations WHERE id = :id', ['id' => $bedId]);
            $db->execute('DELETE FROM patients WHERE id IN (:a, :b)', ['a' => $patientAId, 'b' => $patientBId]);
        }
    }

    private function createPatient(Database $db, string $firstName): int
    {
        $db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, :fn, 'BedRaceTest', '1985-01-01', 'other', :phone, UTC_TIMESTAMP())",
            [
                'pid'   => 'PID-8000-' . random_int(10000, 99999),
                'fn'    => $firstName,
                'phone' => '+2519' . random_int(10000000, 99999999),
            ],
        );

        return $db->lastInsertId();
    }

    private function createBed(Database $db, string $stamp): int
    {
        $db->execute(
            "INSERT INTO ward_locations (ward_name, room_number, bed_number, is_transient, is_occupied, daily_rate)
             VALUES ('Race Test Ward', :room, 'A', 0, 0, '500.00')",
            ['room' => $stamp],
        );

        return $db->lastInsertId();
    }
}
