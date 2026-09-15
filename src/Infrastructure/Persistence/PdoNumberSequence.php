<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Repository\NumberSequenceInterface;

/**
 * MariaDB-native atomic sequence, using the documented
 * INSERT ... ON DUPLICATE KEY UPDATE col = LAST_INSERT_ID(expr) idiom.
 *
 * LAST_INSERT_ID(expr) has a side effect distinct from its return value:
 * it sets the CONNECTION's last-insert-id to expr, evaluated once, and
 * PDO::lastInsertId() reads that same connection-local value straight
 * back. Because the whole statement is a single atomic UPSERT, MariaDB
 * takes the necessary row lock for its duration - two concurrent callers
 * for the same scope serialise on that lock rather than racing, so
 * neither can ever observe or reissue the other's number. Verified with
 * 10 concurrent processes issuing 50 numbers each (500 total, see
 * tests/Feature/Support/NumberSequenceConcurrencyTest.php) with no
 * duplicates and no gaps.
 *
 *   first call for a new scope:  INSERT branch runs.
 *     LAST_INSERT_ID(1) sets id=1 AND evaluates to 1, so next_value
 *     is stored as 1+1=2. Issued: 1.
 *   every call after:            UPDATE branch runs.
 *     LAST_INSERT_ID(next_value) sets id=<current value> and evaluates
 *     to it; +1 is computed OUTSIDE that call (so it does not affect the
 *     id PDO will read) and is what gets stored. Issued: the value that
 *     was stored before this call.
 */
final readonly class PdoNumberSequence implements NumberSequenceInterface
{
    public function __construct(private Database $db)
    {
    }

    public function next(string $scope): int
    {
        $this->db->execute(
            'INSERT INTO number_sequences (scope, next_value)
             VALUES (:scope, LAST_INSERT_ID(1) + 1)
             ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value) + 1',
            ['scope' => $scope],
        );

        return $this->db->lastInsertId();
    }
}
