<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\LedgerEntry;
use Aster\Domain\ValueObject\Balance;

/**
 * consumption_ledger persistence (FRS 7.1).
 *
 * Deliberately no update() and no delete() - see migration 005's own
 * comment. A correction is create()'d as a new row with parent_entry_id
 * set, never an edit to the original.
 */
interface LedgerRepositoryInterface
{
    public function findById(int $id): ?LedgerEntry;

    /** @return list<LedgerEntry> */
    public function forEncounter(int $encounterId): array;

    /**
     * SUM(total_cost) for an encounter, computed in SQL rather than by
     * loading every row into PHP - BillingService::calculateReceivableBalance()
     * is on the hot path for every discharge attempt and every balance
     * display.
     */
    public function sumForEncounter(int $encounterId): Balance;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;
}
