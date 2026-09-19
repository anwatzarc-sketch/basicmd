<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * Account state for a staff user.
 *
 * INVITED means the account exists but the person has never set a password;
 * it cannot authenticate until an administrator activates it. This keeps
 * pre-provisioned accounts from becoming a standing credential risk.
 */
enum UserStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case INVITED   = 'invited';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE    => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INVITED   => 'Invited',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE    => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::SUSPENDED => 'bg-rose-100 text-rose-800 border-rose-200',
            self::INVITED   => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    /** Only an ACTIVE account may authenticate. */
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::ACTIVE, self::INVITED, self::SUSPENDED];
    }
}
