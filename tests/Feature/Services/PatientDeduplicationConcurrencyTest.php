<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Services;

use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Persistence\Database;
use PHPUnit\Framework\TestCase;

/**
 * Proves MPI-005/MPI-007: concurrent registration of the same new patient
 * (matching on no existing row - the exact "two people submit the same
 * new registration at once" race the advisory lock in
 * PatientDeduplicationService exists to close) creates exactly one row,
 * not one per request.
 *
 * Does NOT extend DatabaseTestCase - this is deliberate and load-bearing,
 * not a style choice. On Windows, a parent PHP process that holds ANY open
 * PDO/MySQL connection (transaction or not - a bare connected PDO handle
 * is enough) causes proc_open()'d child processes to silently fail to
 * produce their expected output, with exit code 0 and no error - almost
 * certainly Windows handle inheritance duplicating the parent's live
 * MySQL socket into each child. This was found and confirmed empirically:
 * the same 8-worker spawn succeeds every time from a bare script with no
 * parent-side DB activity, and fails every time the instant the parent
 * calls Database::pdo() or Database::begin() first - reproduced with a
 * transaction, then reproduced again with a plain unconnected... no,
 * plain CONNECTED-but-not-transacted PDO handle, isolating the cause to
 * connection presence, not transaction state.
 *
 * NumberSequenceConcurrencyTest was already written this way for the same
 * reason - this class follows the same pattern for the same reason.
 */
final class PatientDeduplicationConcurrencyTest extends TestCase
{
    private const int WORKERS = 8;

    public function test_concurrent_registration_of_the_same_new_patient_creates_exactly_one_row(): void
    {
        $phone     = '0955' . random_int(100000, 999999);
        $firstName = 'RaceTest' . bin2hex(random_bytes(3));
        $outputDir = sys_get_temp_dir() . '/aster_mpi_race_' . bin2hex(random_bytes(4));
        mkdir($outputDir);

        $workerScript = __DIR__ . '/../Support/patient_dedup_race_worker.php';
        $processes    = [];
        $outputFiles  = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $outputFile    = $outputDir . '/worker_' . $i . '.txt';
            $outputFiles[] = $outputFile;

            $processes[] = proc_open(
                ['php', $workerScript, $firstName, $phone, $outputFile],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
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

        $patientIds = [];

        foreach ($outputFiles as $i => $file) {
            self::assertFileExists($file, "worker {$i} produced no output file");
            $patientIds[] = trim((string) file_get_contents($file));
        }

        self::assertCount(
            1,
            array_unique($patientIds),
            'concurrent registration created more than one patient row for the same identity',
        );

        // Cleanup happens ONLY after every worker has finished (proc_close
        // above already waited for all of them) - opening a connection
        // here, after spawning is fully done, is what NumberSequence's
        // equivalent test does too and does not affect anything already
        // spawned.
        $container = Bootstrap::boot(dirname(__DIR__, 3), cli: true);
        $container->get(Database::class)->execute(
            'DELETE FROM patients WHERE first_name = :fn',
            ['fn' => $firstName],
        );

        array_map('unlink', $outputFiles);
        rmdir($outputDir);
    }
}
