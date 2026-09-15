<?php

declare(strict_types=1);

/**
 * Spawned as a SEPARATE OS process by NumberSequenceConcurrencyTest - a
 * real second (and third, fourth...) database connection is the whole
 * point, since two callers sharing one PHP process could never actually
 * race on the same row the way two independent web requests can.
 *
 * Usage: php number_sequence_race_worker.php <scope> <count> <output-file>
 * Writes one issued integer per line.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Persistence\PdoNumberSequence;

[, $scope, $count, $outputFile] = $argv;

$container = Bootstrap::boot(dirname(__DIR__, 3), cli: true);
$sequence  = $container->get(PdoNumberSequence::class);

$issued = [];

for ($i = 0; $i < (int) $count; $i++) {
    $issued[] = $sequence->next($scope);
}

file_put_contents($outputFile, implode(PHP_EOL, $issued) . PHP_EOL);
