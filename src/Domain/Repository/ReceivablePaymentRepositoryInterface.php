<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\ReceivablePayment;
use Aster\Domain\ValueObject\Balance;
use Aster\Domain\ValueObject\ReceiptId;

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

    /** @param array<string, mixed> $data */
    public function create(array $data): int;
}
