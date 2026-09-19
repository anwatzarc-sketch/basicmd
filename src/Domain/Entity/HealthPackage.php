<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\ValueObject\Money;

/**
 * A prepaid screening package - the direct-checkout revenue product.
 *
 * `items` is the bullet list shown on the pricing card, stored as JSON keyed
 * by locale so marketing can edit the contents without a schema change.
 */
final readonly class HealthPackage
{
    /** @param array<string, list<string>> $items locale => bullet list */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $titleAm,
        public ?string $titleOm,
        public string $slug,
        public Money $price,
        /** Fraction of the price required up front, 0.0-1.0. */
        public float $depositRate,
        public ?string $description,
        public ?string $descriptionAm,
        public ?string $descriptionOm,
        public array $items,
        public ?string $badge,
        public bool $isFeatured,
        public bool $isActive,
        public int $sortOrder,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $items = [];

        if (isset($row['items_json']) && is_string($row['items_json']) && $row['items_json'] !== '') {
            // json_validate() first so a corrupted column degrades to an
            // empty list instead of throwing on a public pricing page.
            if (json_validate($row['items_json'])) {
                $decoded = json_decode($row['items_json'], true);

                if (is_array($decoded)) {
                    foreach ($decoded as $locale => $list) {
                        if (is_array($list)) {
                            $items[(string) $locale] = array_values(array_filter(
                                array_map(static fn (mixed $i): string => is_scalar($i) ? (string) $i : '', $list),
                                static fn (string $i): bool => $i !== '',
                            ));
                        }
                    }
                }
            }
        }

        return new self(
            id:            (int) $row['id'],
            title:         (string) $row['title'],
            titleAm:       self::nullableString($row['title_am'] ?? null),
            titleOm:       self::nullableString($row['title_om'] ?? null),
            slug:          (string) $row['slug'],
            price:         Money::fromDatabase($row['price_etb'] ?? 0),
            depositRate:   (float) ($row['deposit_rate'] ?? 0.30),
            description:   self::nullableString($row['description'] ?? null),
            descriptionAm: self::nullableString($row['description_am'] ?? null),
            descriptionOm: self::nullableString($row['description_om'] ?? null),
            items:         $items,
            badge:         self::nullableString($row['badge'] ?? null),
            isFeatured:    (bool) ($row['is_featured'] ?? false),
            isActive:      ($row['status'] ?? 'active') === 'active',
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
     * back to the OTHER translation: an untranslated Afaan Oromo package
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

    public function name(Locale $locale): string
    {
        return $this->translation($locale, $this->titleAm, $this->titleOm) ?? $this->title;
    }

    public function summary(Locale $locale): ?string
    {
        return $this->translation($locale, $this->descriptionAm, $this->descriptionOm)
            ?? $this->description;
    }

    /**
     * Bullet list for a locale, falling back to English when the translated
     * list has not been filled in.
     *
     * @return list<string>
     */
    public function itemList(Locale $locale): array
    {
        $list = $this->items[$locale->value] ?? [];

        return $list !== [] ? $list : ($this->items['en'] ?? []);
    }

    /** The up-front amount required to hold a slot. */
    public function depositAmount(): Money
    {
        return $this->price->multiply($this->depositRate);
    }

    public function balanceAmount(): Money
    {
        return $this->price->subtract($this->depositAmount());
    }

    public function depositPercentLabel(): string
    {
        return round($this->depositRate * 100) . '%';
    }

    public function requiresDeposit(): bool
    {
        return $this->depositRate > 0.0 && $this->price->isPositive();
    }
}
