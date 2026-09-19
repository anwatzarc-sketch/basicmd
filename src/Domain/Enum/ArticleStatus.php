<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * Editorial workflow for the Health Knowledge Hub.
 *
 *      draft ──► review ──► published ──► archived
 *        ▲          │            │            │
 *        └──────────┴────────────┴────────────┘   (may return to draft)
 *
 * `review` exists because medical content needs clinician sign-off before it
 * goes public - that is what makes the hub credible to search engines and
 * safe for patients.
 */
enum ArticleStatus: string
{
    case DRAFT     = 'draft';
    case REVIEW    = 'review';
    case PUBLISHED = 'published';
    case ARCHIVED  = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::REVIEW    => 'Awaiting Clinical Review',
            self::PUBLISHED => 'Published',
            self::ARCHIVED  => 'Archived',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT     => 'bg-slate-100 text-slate-700 border-slate-200',
            self::REVIEW    => 'bg-amber-100 text-amber-800 border-amber-200',
            self::PUBLISHED => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::ARCHIVED  => 'bg-zinc-200 text-zinc-700 border-zinc-300',
        };
    }

    /** Visible on the public site and included in the sitemap. */
    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT     => [self::REVIEW, self::PUBLISHED, self::ARCHIVED],
            self::REVIEW    => [self::PUBLISHED, self::DRAFT, self::ARCHIVED],
            self::PUBLISHED => [self::ARCHIVED, self::DRAFT],
            self::ARCHIVED  => [self::DRAFT, self::PUBLISHED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Moving into this state needs the articles.publish permission. */
    public function requiresPublishPermission(): bool
    {
        return $this === self::PUBLISHED;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::DRAFT, self::REVIEW, self::PUBLISHED, self::ARCHIVED];
    }
}
