<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Feature\Support;

use MediCareMini\Infrastructure\Container\Bootstrap;
use MediCareMini\Infrastructure\Persistence\Database;
use PHPUnit\Framework\TestCase;

/**
 * Proves PdoNumberSequence is race-free under REAL concurrent processes -
 * not merely concurrent requests within one PHP process, which could never
 * demonstrate the row-locking behaviour that makes this safe. Every
 * identifier the FRS calls "collision-safe" (PatientId, VisitNumber, and
 * the Stage 3 receipt id) depends on this holding.
 *
 * Does not extend DatabaseTestCase: each worker needs its OWN database
 * connection in its OWN OS process, which a shared test-transaction
 * wrapper would defeat entirely (and DDL/connection concerns aside, the
 * whole point is to prove independent connections cannot collide).
 */
final class NumberSequenceConcurrencyTest extends TestCase
{
    private const int WORKERS          = 10;
    private const int PER_WORKER_COUNT = 50;

    public function test_concurrent_processes_never_receive_the_same_number(): void
    {
        $scope     = 'test-race-' . bin2hex(random_bytes(4));
        $outputDir = sys_get_temp_dir() . '/medicaremini_seq_race_' . bin2hex(random_bytes(4));
        mkdir($outputDir);

        $workerScript = __DIR__ . '/number_sequence_race_worker.php';
        $processes    = [];
        $outputFiles  = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $outputFile    = $outputDir . '/worker_' . $i . '.txt';
            $outputFiles[] = $outputFile;

            $processes[] = proc_open(
                ['php', $workerScript, $scope, (string) self::PER_WORKER_COUNT, $outputFile],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );

            // Drain stderr/stdout so a worker can never block on a full pipe
            // buffer while this test waits for it.
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        $exitCodes = [];

        foreach ($processes as $process) {
            $exitCodes[] = proc_close($process);
        }

        foreach ($exitCodes as $i => $code) {
            self::assertSame(0, $code, "worker {$i} exited non-zero");
        }

        $allIssued = [];

        foreach ($outputFiles as $file) {
            self::assertFileExists($file);

            $lines = array_filter(explode(PHP_EOL, trim((string) file_get_contents($file))));
            self::assertCount(self::PER_WORKER_COUNT, $lines, "worker file {$file} has the wrong count");

            $allIssued = [...$allIssued, ...array_map('intval', $lines)];
        }

        $expectedTotal = self::WORKERS * self::PER_WORKER_COUNT;

        self::assertCount($expectedTotal, $allIssued, 'total numbers issued across all workers');
        self::assertCount(
            $expectedTotal,
            array_unique($allIssued),
            'duplicate number issued to two different processes - the sequence is not race-free',
        );

        sort($allIssued);
        self::assertSame(
            range(1, $expectedTotal),
            $allIssued,
            'issued numbers must be exactly 1..N with no gaps',
        );

        // Cleanup: remove the test scope's row and the temp output files.
        $container = Bootstrap::boot(dirname(__DIR__, 3), cli: true);
        $container->get(Database::class)->execute(
            'DELETE FROM number_sequences WHERE scope = :s',
            ['s' => $scope],
        );

        array_map('unlink', $outputFiles);
        rmdir($outputDir);
    }
}
