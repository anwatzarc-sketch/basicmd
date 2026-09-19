<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/** The five note types FRS 6.1 defines. */
enum ClinicalNoteType: string
{
    case TRIAGE            = 'triage';
    case VITALS             = 'vitals';
    case CHIEF_COMPLAINT    = 'chief_complaint';
    case PROGRESS_NOTE      = 'progress_note';
    case DISCHARGE_SUMMARY  = 'discharge_summary';

    public function label(): string
    {
        return match ($this) {
            self::TRIAGE            => 'Triage',
            self::VITALS             => 'Vitals',
            self::CHIEF_COMPLAINT    => 'Chief Complaint',
            self::PROGRESS_NOTE      => 'Progress Note',
            self::DISCHARGE_SUMMARY  => 'Discharge Summary',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::TRIAGE,
            self::VITALS,
            self::CHIEF_COMPLAINT,
            self::PROGRESS_NOTE,
            self::DISCHARGE_SUMMARY,
        ];
    }
}
