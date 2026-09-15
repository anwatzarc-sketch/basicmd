<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/** FRS 5.5 / 8.3: the discharge/closure gate's own state. */
enum FinancialClearanceStatus: string
{
    case PENDING    = 'PENDING';
    case APPROVED   = 'APPROVED';
    case OVERRIDDEN = 'OVERRIDDEN';

    public function label(): string
    {
        return match ($this) {
            self::PENDING    => 'Pending',
            self::APPROVED   => 'Approved',
            self::OVERRIDDEN => 'Overridden',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::PENDING, self::APPROVED, self::OVERRIDDEN];
    }
}
