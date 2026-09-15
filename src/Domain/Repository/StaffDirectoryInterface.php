<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

/**
 * The one staff-account fact EncounterService needs: whether a given user
 * id is an active physician, before letting it be assigned as an
 * encounter's primary_physician_id.
 *
 * Deliberately narrow rather than a full UserRepositoryInterface port - a
 * foreign key already guarantees the id exists in `users`; what it cannot
 * guarantee is that the id belongs to someone clinically authorised to be
 * a primary physician, which is the actual business rule FRS 8.2's
 * "physician must be valid and authorized" precondition is asking for.
 */
interface StaffDirectoryInterface
{
    public function isActivePhysician(int $userId): bool;

    /**
     * Whether $userId is any active, non-deleted staff account - used by
     * BillingService::processDischargeOrClosure() as a basic sanity check
     * on the actor performing the discharge (FRS 8.3: "Validate that the
     * actor is authorized"). The actual PERMISSION decision - who is
     * allowed to call discharge at all, and who is allowed to apply a
     * financial-clearance override - is enforced by route-level `can:`
     * middleware in the Presentation layer, the same separation of
     * concerns EncounterService already relies on for
     * upgradeOpdToIpd(); this method only catches a bogus or deleted
     * user id reaching the Domain layer at all.
     */
    public function isActiveStaffUser(int $userId): bool;
}
