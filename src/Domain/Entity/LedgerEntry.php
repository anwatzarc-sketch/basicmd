<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\LedgerCategory;
use MediCareMini\Domain\Enum\LedgerMode;
use MediCareMini\Domain\ValueObject\Balance;
use DateTimeImmutable;

/**
 * One itemized charge on the consumption ledger (FRS 7.1).
 *
 * totalCost is a Balance, not Money: an ORIGINAL charge is always
 * positive, but a CORRECTING entry (parentEntryId set) is negative by
 * construction - see migration 005's own comment for why the sign lives
 * on the row, not on a separate "is this a reversal" flag. perUnitCost
 * is likewise signed for the same reason; unit itself never is (FRS 7.1
 * LED-004: quantity is unsigned).
 */
final readonly class LedgerEntry
{
    public function __construct(
        public int $id,
        public int $encounterId,
        public int $accountantId,
        public DateTimeImmutable $entryDate,
        public LedgerCategory $category,
        public string $costEntry,
        public int $unit,
        public Balance $perUnitCost,
        public Balance $totalCost,
        public string $reason,
        public LedgerMode $mode,
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
            encounterId:     (int) $row['encounter_id'],
            accountantId:    (int) $row['accountant_id'],
            entryDate:       self::toDate($row['entry_date'] ?? null) ?? new DateTimeImmutable(),
            category:        LedgerCategory::from((string) $row['category']),
            costEntry:       (string) $row['cost_entry'],
            unit:            (int) $row['unit'],
            perUnitCost:     Balance::fromDatabase($row['per_unit_cost'] ?? 0),
            totalCost:       Balance::fromDatabase($row['total_cost'] ?? 0),
            reason:          (string) $row['reason'],
            mode:            LedgerMode::from((string) $row['mode']),
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

    public function isReversal(): bool
    {
        return $this->parentEntryId !== null;
    }
}
