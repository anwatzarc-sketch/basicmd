<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use MediCareMini\Domain\Entity\ReceivablePayment;
use MediCareMini\Domain\ValueObject\Balance;
use MediCareMini\Domain\ValueObject\ReceiptId;

/**
 * receivable_payments persistence (FRS 7.2). Insert-only, like
 * LedgerRepositoryInterface - see migration 005's comment.
 */
interface ReceivablePaymentRepositoryInterface
{
    public function findById(int $id): ?ReceivablePayment;

    public function findByReceiptId(ReceiptId $receiptId): ?ReceivablePayment;

    /** @return list<ReceivablePayment> */
    public function forEncounter(int $encounterId): array;

    /** SUM(amount_paid) for an encounter, computed in SQL. */
    public function sumForEncounter(int $encounterId): Balance;

    /**
     * SUM(amount_paid) across every encounter a patient has ever had - the
     * Patient Detail Financial panel's "Payments" figure (spec §5).
     */
    public function sumForPatient(int $patientId): Balance;

    /** @return list<ReceivablePayment> */
    public function forPatient(int $patientId, int $limit = 200): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;
}
