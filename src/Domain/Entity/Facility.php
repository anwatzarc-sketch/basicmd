<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\FacilityStatus;
use MediCareMini\Domain\Enum\Locale;

final readonly class Facility
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $nameAm,
        public ?string $nameOm,
        public string $type,
        public ?string $roomLabel,
        public ?string $description,
        public ?string $descriptionAm,
        public ?string $descriptionOm,
        public ?string $imagePath,
        public FacilityStatus $status,
        public ?string $notes,
        public bool $isPublic,
        public int $sortOrder,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            name:          (string) $row['fac_name'],
            nameAm:        self::nullableString($row['name_am'] ?? null),
            nameOm:        self::nullableString($row['name_om'] ?? null),
            type:          (string) $row['type'],
            roomLabel:     self::nullableString($row['room_label'] ?? null),
            description:   self::nullableString($row['description'] ?? null),
            descriptionAm: self::nullableString($row['description_am'] ?? null),
            descriptionOm: self::nullableString($row['description_om'] ?? null),
            imagePath:     self::nullableString($row['image_path'] ?? null),
            status:        FacilityStatus::from((string) ($row['status'] ?? 'operational')),
            notes:         self::nullableString($row['notes'] ?? null),
            isPublic:      (bool) ($row['is_public'] ?? true),
            sortOrder:     (int) ($row['sort_order'] ?? 0),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The translation for a locale, or null when there is not a usable one.
     *
     * English is the base column and therefore never has a translation of
     * its own; a locale whose column is NULL or empty also returns null so
     * the caller can fall back with `?? $base`. Deliberately never falls
     * back to the OTHER translation: an untranslated Afaan Oromo entry
     * degrades to readable English, not to Ge'ez script.
     */
    private function translation(Locale $locale, ?string $amharic, ?string $oromo): ?string
    {
        $value = match ($locale) {
            Locale::EN => null,
            Locale::AM => $amharic,
            Locale::OM => $oromo,
        };

        return $value !== null && $value !== '' ? $value : null;
    }

    public function title(Locale $locale): string
    {
        return $this->translation($locale, $this->nameAm, $this->nameOm) ?? $this->name;
    }

    public function summary(Locale $locale): ?string
    {
        return $this->translation($locale, $this->descriptionAm, $this->descriptionOm)
            ?? $this->description;
    }

    /** Shown on the public site only when flagged public AND not offline. */
    public function showsPublicly(): bool
    {
        return $this->isPublic && $this->status->isPubliclyVisible();
    }
}
