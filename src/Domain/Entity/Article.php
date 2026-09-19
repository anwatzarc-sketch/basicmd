<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\ArticleStatus;
use Aster\Domain\Enum\Locale;
use Aster\Domain\Enum\SchemaType;
use DateTimeImmutable;

/**
 * A Health Knowledge Hub article - the organic-search acquisition engine.
 *
 * Carries its own SEO metadata and a schema.org type, so each page can emit
 * targeted JSON-LD rather than one generic blob.
 */
final readonly class Article
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $titleAm,
        public ?string $titleOm,
        public string $slug,
        public string $category,
        public ?string $excerpt,
        public ?string $excerptAm,
        public ?string $excerptOm,
        public ?string $content,
        public ?string $contentAm,
        public ?string $contentOm,
        public ?string $coverImage,
        public ?string $coverAlt,
        public ?int $authorId,
        public ?int $reviewerId,
        public SchemaType $schemaType,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyword,
        public int $readMinutes,
        public int $views,
        public ArticleStatus $status,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        // --- Denormalised from the repository JOIN ---
        public ?string $authorName = null,
        public ?string $authorSpecialty = null,
        public ?string $reviewerName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            title:           (string) $row['title'],
            titleAm:         self::nullableString($row['title_am'] ?? null),
            titleOm:         self::nullableString($row['title_om'] ?? null),
            slug:            (string) $row['slug'],
            category:        (string) ($row['category'] ?? 'General'),
            excerpt:         self::nullableString($row['excerpt'] ?? null),
            excerptAm:       self::nullableString($row['excerpt_am'] ?? null),
            excerptOm:       self::nullableString($row['excerpt_om'] ?? null),
            content:         self::nullableString($row['content'] ?? null),
            contentAm:       self::nullableString($row['content_am'] ?? null),
            contentOm:       self::nullableString($row['content_om'] ?? null),
            coverImage:      self::nullableString($row['cover_image'] ?? null),
            coverAlt:        self::nullableString($row['cover_alt'] ?? null),
            authorId:        self::nullableInt($row['author_id'] ?? null),
            reviewerId:      self::nullableInt($row['reviewer_id'] ?? null),
            schemaType:      SchemaType::from((string) ($row['schema_type'] ?? 'MedicalWebPage')),
            metaTitle:       self::nullableString($row['meta_title'] ?? null),
            metaDescription: self::nullableString($row['meta_description'] ?? null),
            focusKeyword:    self::nullableString($row['focus_keyword'] ?? null),
            readMinutes:     (int) ($row['read_minutes'] ?? 3),
            views:           (int) ($row['views'] ?? 0),
            status:          ArticleStatus::from((string) ($row['status'] ?? 'draft')),
            publishedAt:     self::toDate($row['published_at'] ?? null),
            updatedAt:       self::toDate($row['updated_at'] ?? null) ?? new DateTimeImmutable(),
            authorName:      self::nullableString($row['author_name'] ?? null),
            authorSpecialty: self::nullableString($row['author_specialty'] ?? null),
            reviewerName:    self::nullableString($row['reviewer_name'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value . ' UTC') : null;
    }

    /**
     * The translation for a locale, or null when there is not a usable one.
     *
     * English is the base column and therefore never has a translation of
     * its own; a locale whose column is NULL or empty also returns null so
     * the caller can fall back with `?? $base`. Deliberately never falls
     * back to the OTHER translation: an untranslated Afaan Oromo article
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

    public function heading(Locale $locale): string
    {
        return $this->translation($locale, $this->titleAm, $this->titleOm) ?? $this->title;
    }

    public function summary(Locale $locale): ?string
    {
        return $this->translation($locale, $this->excerptAm, $this->excerptOm) ?? $this->excerpt;
    }

    public function body(Locale $locale): ?string
    {
        return $this->translation($locale, $this->contentAm, $this->contentOm) ?? $this->content;
    }

    /**
     * True when this article has a usable translation in a given language -
     * both a headline and a body, since a translated title over English
     * prose reads as a bug rather than as a translation.
     *
     * Defaults to Amharic, which is what the single-argument-free callers
     * predating Afaan Oromo meant by "translated".
     */
    public function hasTranslation(Locale $locale = Locale::AM): bool
    {
        return $this->translation($locale, $this->titleAm, $this->titleOm) !== null
            && $this->translation($locale, $this->contentAm, $this->contentOm) !== null;
    }

    /** <title> value: the SEO override if set, otherwise the headline. */
    public function seoTitle(Locale $locale): string
    {
        return $this->metaTitle ?? $this->heading($locale);
    }

    public function seoDescription(Locale $locale): string
    {
        if ($this->metaDescription !== null) {
            return $this->metaDescription;
        }

        $summary = $this->summary($locale) ?? '';

        return mb_substr(trim(strip_tags($summary)), 0, 155);
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic();
    }

    public function categorySlug(): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $this->category) ?? '');
    }

    /**
     * Reading time, computed from the body when the column was left at its
     * default. Ge'ez script is not word-delimited the way Latin text is, so
     * Amharic is estimated by character count instead.
     */
    public function estimatedReadMinutes(Locale $locale = Locale::EN): int
    {
        if ($this->readMinutes > 0) {
            return $this->readMinutes;
        }

        $text = strip_tags($this->body($locale) ?? '');

        $minutes = $locale === Locale::AM
            ? (int) ceil(mb_strlen($text) / 900)
            : (int) ceil(str_word_count($text) / 200);

        return max(1, $minutes);
    }

    public function hasCover(): bool
    {
        return $this->coverImage !== null;
    }
}
