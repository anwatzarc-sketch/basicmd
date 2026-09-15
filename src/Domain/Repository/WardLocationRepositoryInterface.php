<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\WardLocation;

/**
 * Bed/room persistence port for EncounterService's admission workflow.
 *
 * findForUpdate() is the concurrency-critical method: it must take a
 * SELECT ... FOR UPDATE row lock, mirroring the pattern
 * AppointmentRepository::createWithCapacityCheck() already uses for the
 * doctor row - lock first, re-check occupancy AFTER the lock is held, only
 * then mutate. The lock itself is an Infrastructure/SQL concern and does
 * not appear in this interface; only the caller-visible contract
 * ("this call may block until it can safely read a consistent value")
 * does.
 */
interface WardLocationRepositoryInterface
{
    public function find(int $id): ?WardLocation;

    /**
     * Same as find(), but takes a row lock that is held until the
     * enclosing transaction commits or rolls back. Must only be called
     * inside a TransactionManagerInterface::transactional() callback.
     */
    public function findForUpdate(int $id): ?WardLocation;

    public function markOccupied(int $id): void;

    public function markAvailable(int $id): void;
}
