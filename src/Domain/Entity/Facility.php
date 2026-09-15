<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\FacilityStatus;
use Aster\Domain\Enum\Locale;

final readonly class Facility
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $nameAm,
        public string $type,
        public ?string $roomLabel,
        public ?string $description,
        public ?string $descriptionAm,
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
            name:          (string) $row['name'],
            nameAm:        self::nullableString($row['name_am'] ?? null),
            type:          (string) $row['type'],
            roomLabel:     self::nullableString($row['room_label'] ?? null),
            description:   self::nullableString($row['description'] ?? null),
            descriptionAm: self::nullableString($row['description_am'] ?? null),
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

    public function title(Locale $locale): string
    {
        return $locale === Locale::AM && $this->nameAm !== null ? $this->nameAm : $this->name;
    }

    public function summary(Locale $locale): ?string
    {
        return $locale === Locale::AM && $this->descriptionAm !== null
            ? $this->descriptionAm
            : $this->description;
    }

    /** Shown on the public site only when flagged public AND not offline. */
    public function showsPublicly(): bool
    {
        return $this->isPublic && $this->status->isPubliclyVisible();
    }
}
