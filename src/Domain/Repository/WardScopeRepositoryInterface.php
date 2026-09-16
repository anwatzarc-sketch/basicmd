<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

/**
 * Port onto `user_role_scopes` (spec §4.5) - the ward-scoping half of
 * permission resolution, kept separate from RoleRepository because it is
 * read on (almost) every Patient Detail request, by WardScopeService,
 * rather than only from the Role Management admin screen.
 */
interface WardScopeRepositoryInterface
{
    /**
     * True when this user holds at least one role grant with NO
     * user_role_scopes rows - including holding no user_roles rows at
     * all. Either case is "unscoped" per spec §4.5: sees every patient
     * for whatever permission that grant carries, unchanged from today.
     */
    public function hasUnscopedGrant(int $userId): bool;

    /**
     * The union of every ward_name this user is scoped to, across all of
     * their role grants that DO carry a user_role_scopes row. Only
     * meaningful when hasUnscopedGrant() is false.
     *
     * @return list<string>
     */
    public function wardNamesForUser(int $userId): array;
}
