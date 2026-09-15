<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

enum InquiryStatus: string
{
    case UNREAD      = 'unread';
    case IN_PROGRESS = 'in_progress';
    case RESPONDED   = 'responded';
    case ARCHIVED    = 'archived';
    case SPAM        = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::UNREAD      => 'Unread',
            self::IN_PROGRESS => 'In Progress',
            self::RESPONDED   => 'Responded',
            self::ARCHIVED    => 'Archived',
            self::SPAM        => 'Spam',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::UNREAD      => 'bg-amber-100 text-amber-800 border-amber-200',
            self::IN_PROGRESS => 'bg-sky-100 text-sky-800 border-sky-200',
            self::RESPONDED   => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::ARCHIVED    => 'bg-slate-100 text-slate-600 border-slate-200',
            self::SPAM        => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }

    /** Counts toward the sidebar badge. */
    public function needsAttention(): bool
    {
        return match ($this) {
            self::UNREAD, self::IN_PROGRESS => true,
            self::RESPONDED, self::ARCHIVED, self::SPAM => false,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::UNREAD, self::IN_PROGRESS, self::RESPONDED, self::ARCHIVED, self::SPAM];
    }
}
