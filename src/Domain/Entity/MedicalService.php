<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\Locale;
use Aster\Domain\Enum\ServiceCategory;
use Aster\Domain\ValueObject\Money;

/**
 * A clinical service in the public catalogue.
 *
 * Named MedicalService rather than Service because "Service" collides with
 * the application-service layer and makes every `use` statement ambiguous.
 */
final readonly class MedicalService
{
    public function __construct(
        public int $id,
        public string $icon,
        public string $name,
        public ?string $nameAm,
        public ?string $nameOm,
        public string $slug,
        public ?string $description,
        public ?string $descriptionAm,
        public ?string $descriptionOm,
        public ServiceCategory $category,
        public Money $price,
        public int $durationMinutes,
        public bool $isFeatured,
        public bool $isActive,
        public int $sortOrder,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            icon:            (string) ($row['icon'] ?? ''),
            name:            (string) $row['ser_name'],
            nameAm:          self::nullableString($row['name_am'] ?? null),
            nameOm:          self::nullableString($row['name_om'] ?? null),
            slug:            (string) $row['slug'],
            description:     self::nullableString($row['description'] ?? null),
            descriptionAm:   self::nullableString($row['description_am'] ?? null),
            descriptionOm:   self::nullableString($row['description_om'] ?? null),
            category:        ServiceCategory::from((string) ($row['category'] ?? 'clinical')),
            price:           Money::fromDatabase($row['price'] ?? 0),
            durationMinutes: (int) ($row['duration_min'] ?? 30),
            isFeatured:      (bool) ($row['is_featured'] ?? false),
            isActive:        ($row['status'] ?? 'active') === 'active',
            sortOrder:       (int) ($row['sort_order'] ?? 0),
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
     * back to the OTHER translation: an untranslated Afaan Oromo service
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

    /** Free services (pharmacy, emergency triage) show no price badge. */
    public function hasPrice(): bool
    {
        return $this->price->isPositive();
    }

    public function durationLabel(): string
    {
        if ($this->durationMinutes < 60) {
            return $this->durationMinutes . ' min';
        }

        $hours   = intdiv($this->durationMinutes, 60);
        $minutes = $this->durationMinutes % 60;

        return $minutes === 0 ? $hours . ' hr' : $hours . ' hr ' . $minutes . ' min';
    }
}
