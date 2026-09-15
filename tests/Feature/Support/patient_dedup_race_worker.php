<?php

declare(strict_types=1);

/**
 * Spawned as a separate OS process by
 * PatientDeduplicationServiceTest::test_concurrent_registration_of_the_same_new_patient_creates_exactly_one_row().
 *
 * Usage: php patient_dedup_race_worker.php <firstName> <phone> <output-file>
 * All workers submit IDENTICAL registration data for a patient who does
 * not exist yet - the advisory lock inside findOrRegister() must ensure
 * exactly one of them creates the row and the rest are handed back the
 * same one.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Aster\Domain\DTO\PatientDTO;
use Aster\Domain\Enum\Gender;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Domain\ValueObject\PhoneNumber;
use Aster\Infrastructure\Container\Bootstrap;

[, $firstName, $phone, $outputFile] = $argv;

$container = Bootstrap::boot(dirname(__DIR__, 3), cli: true);
$service   = $container->get(PatientDeduplicationService::class);

$dto = new PatientDTO(
    firstName:   $firstName,
    lastName:    'RaceWorker',
    dateOfBirth: new DateTimeImmutable('1995-07-07'),
    gender:      Gender::OTHER,
    phoneNumber: PhoneNumber::fromString($phone),
);

$patient = $service->findOrRegister($dto);

file_put_contents($outputFile, (string) $patient->id);
