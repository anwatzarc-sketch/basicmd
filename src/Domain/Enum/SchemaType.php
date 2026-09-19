<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * schema.org type emitted as JSON-LD for an article.
 *
 * Choosing the right type is the whole point of the SEO engine: Google
 * renders MedicalCondition and MedicalProcedure pages with richer health
 * panels than a plain Article, which is what wins high-intent searches like
 * "high blood pressure treatment Addis Ababa".
 */
enum SchemaType: string
{
    case MEDICAL_WEB_PAGE  = 'MedicalWebPage';
    case MEDICAL_CONDITION = 'MedicalCondition';
    case MEDICAL_PROCEDURE = 'MedicalProcedure';
    case ARTICLE           = 'Article';

    public function label(): string
    {
        return match ($this) {
            self::MEDICAL_WEB_PAGE  => 'Medical Web Page (general health guidance)',
            self::MEDICAL_CONDITION => 'Medical Condition (a specific illness)',
            self::MEDICAL_PROCEDURE => 'Medical Procedure (a treatment or test)',
            self::ARTICLE           => 'Article (news, clinic updates)',
        };
    }

    /** Short hint rendered next to the selector in the CMS. */
    public function hint(): string
    {
        return match ($this) {
            self::MEDICAL_WEB_PAGE  => 'Best default for prevention and wellness guides.',
            self::MEDICAL_CONDITION => 'Use when the page is about one named condition.',
            self::MEDICAL_PROCEDURE => 'Use for screening, imaging or treatment explainers.',
            self::ARTICLE           => 'Use for non-clinical announcements.',
        };
    }

    /**
     * Whether Google expects the medical-specific properties
     * (lastReviewed, reviewedBy) on this type.
     */
    public function supportsClinicalReview(): bool
    {
        return $this !== self::ARTICLE;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::MEDICAL_WEB_PAGE,
            self::MEDICAL_CONDITION,
            self::MEDICAL_PROCEDURE,
            self::ARTICLE,
        ];
    }
}
