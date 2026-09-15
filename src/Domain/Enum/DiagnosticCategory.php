<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/** The three diagnostic categories FRS 6.2 defines. */
enum DiagnosticCategory: string
{
    case LAB     = 'Lab';
    case IMAGING = 'Imaging';
    case PACS    = 'PACS';

    /** @return list<self> */
    public static function all(): array
    {
        return [self::LAB, self::IMAGING, self::PACS];
    }
}
