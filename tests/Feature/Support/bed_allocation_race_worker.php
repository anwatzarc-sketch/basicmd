<?php

declare(strict_types=1);

/**
 * Spawned as a separate OS process by BedAllocationConcurrencyTest.
 *
 * Usage: php bed_allocation_race_worker.php <visitNumber> <bedId> <physicianId> <output-file>
 * Writes "OK" then exits 0 on a successful admission, or writes the
 * EncounterException's reason code and exits 0 on an expected rejection -
 * a REJECTION is not a worker failure, it is the whole point of the test,
 * so it must not be confused with an actual crash (a non-zero exit code,
 * which the test treats as a real failure).
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use MediCareMini\Domain\Exception\EncounterException;
use MediCareMini\Domain\Services\EncounterService;
use MediCareMini\Domain\ValueObject\VisitNumber;
use MediCareMini\Infrastructure\Container\Bootstrap;

[, $visitNumberValue, $bedId, $physicianId, $outputFile] = $argv;

$container = Bootstrap::boot(dirname(__DIR__, 3), cli: true);
$service   = $container->get(EncounterService::class);

try {
    $service->upgradeOpdToIpd(
        VisitNumber::fromString($visitNumberValue),
        (int) $bedId,
        (int) $physicianId,
    );

    file_put_contents($outputFile, 'OK');
} catch (EncounterException $e) {
    file_put_contents($outputFile, 'REJECTED:' . $e->reason);
}
