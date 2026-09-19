<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use MediCareMini\Domain\Entity\LedgerEntry;
use MediCareMini\Domain\ValueObject\Balance;

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

    /**
     * SUM(total_cost) across every encounter a patient has ever had - the
     * Patient Detail Financial panel's "Charges" figure (spec §5), joined
     * through encounters rather than summed per-encounter in PHP for the
     * same reason sumForEncounter() itself is computed in SQL.
     */
    public function sumForPatient(int $patientId): Balance;

    /** @return list<LedgerEntry> */
    public function forPatient(int $patientId, int $limit = 200): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;
}
