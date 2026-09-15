<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

/**
 * Atomic, collision-safe integer sequence generator, scoped by an
 * arbitrary string key (e.g. "pid:2026", "visit:20260915").
 *
 * Backs PatientId, VisitNumber and (in Stage 3) the receipt id - the FRS
 * requires each of those to come from "a collision-safe mechanism" and
 * explicitly rules out a non-atomic read-and-increment. A port in the
 * domain layer, implemented with real SQL in Infrastructure, per the
 * "ports in Domain, PDO in Infrastructure" decision - a domain service
 * needs to issue a number without knowing how.
 */
interface NumberSequenceInterface
{
    /**
     * Issue the next integer for $scope, starting at 1 on first use.
     *
     * Must be safe under concurrent callers: two simultaneous calls for
     * the same scope must never return the same value.
     */
    public function next(string $scope): int;
}
