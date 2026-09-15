<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\ReceivablePaymentMethod;
use Aster\Domain\ValueObject\Balance;
use DateTimeImmutable;

/**
 * One payment posted directly against an encounter's receivable balance
 * (FRS 7.2). Distinct from the existing Payment entity, which represents
 * a patient-uploaded proof of transfer awaiting Finance's review on the
 * ORIGINAL booking-payment flow - this one is entered directly by an
 * accountant and needs no separate verification step (see migration
 * 005's comment on why "posting is verification" here).
 */
final readonly class ReceivablePayment
{
    public function __construct(
        public int $id,
        public string $receiptId,
        public int $encounterId,
        public int $accountantId,
        public DateTimeImmutable $paymentDate,
        public Balance $amountPaid,
        public ReceivablePaymentMethod $paymentMethod,
        public ?string $referenceNote,
        public ?int $parentEntryId,
        public DateTimeImmutable $createdAt,
        public ?string $accountantName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            receiptId:       (string) $row['receipt_id'],
            encounterId:     (int) $row['encounter_id'],
            accountantId:    (int) $row['accountant_id'],
            paymentDate:     self::toDate($row['payment_date'] ?? null) ?? new DateTimeImmutable(),
            amountPaid:      Balance::fromDatabase($row['amount_paid'] ?? 0),
            paymentMethod:   ReceivablePaymentMethod::from((string) $row['payment_method']),
            referenceNote:   isset($row['reference_note']) && $row['reference_note'] !== null ? (string) $row['reference_note'] : null,
            parentEntryId:   isset($row['parent_entry_id']) && $row['parent_entry_id'] !== null ? (int) $row['parent_entry_id'] : null,
            createdAt:       self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            accountantName:  isset($row['accountant_name']) ? (string) $row['accountant_name'] : null,
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    public function isRefund(): bool
    {
        return $this->parentEntryId !== null;
    }
}
