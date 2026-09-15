<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\ClinicalNoteType;
use DateTimeImmutable;

/**
 * A single clinical documentation entry (FRS 6.1).
 *
 * contentEncrypted carries CIPHERTEXT - the entity never decrypts itself,
 * the same boundary Patient::allergiesEncrypted draws and for the same
 * reason (Domain must not depend on the concrete Encryptor). Notes are
 * insert-only by design: there is no update path anywhere in this
 * codebase for a clinical_notes row (see ClinicalNoteRepositoryInterface's
 * docblock) - a correction is a new note, not an edit to an old one,
 * which trivially satisfies FRS 6.1's "must not be assigned to a
 * different encounter after creation" since encounter_id can never
 * change if nothing about the row can.
 */
final readonly class ClinicalNote
{
    public function __construct(
        public int $id,
        public int $encounterId,
        public int $authorId,
        public ClinicalNoteType $noteType,
        /** @var array<string, mixed>|null */
        public ?array $vitals,
        public string $contentEncrypted,
        public ?string $icdCode,
        public DateTimeImmutable $createdAt,
        // Denormalised display label, read-only, never written back.
        public ?string $authorName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $vitals = null;

        if (isset($row['vitals_json']) && is_string($row['vitals_json']) && $row['vitals_json'] !== '') {
            $decoded = json_decode($row['vitals_json'], true);
            $vitals  = is_array($decoded) ? $decoded : null;
        }

        return new self(
            id:                (int) $row['id'],
            encounterId:       (int) $row['encounter_id'],
            authorId:          (int) $row['author_id'],
            noteType:          ClinicalNoteType::from((string) $row['note_type']),
            vitals:            $vitals,
            contentEncrypted:  (string) $row['content_encrypted'],
            icdCode:           isset($row['icd_code']) && $row['icd_code'] !== null ? (string) $row['icd_code'] : null,
            createdAt:         self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            authorName:        isset($row['author_name']) ? (string) $row['author_name'] : null,
        );
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }
}
