<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Services;

use MediCareMini\Domain\Entity\User;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\WardLocationRepositoryInterface;
use MediCareMini\Domain\Repository\WardScopeRepositoryInterface;

/**
 * Ward-scoped access (spec §4.5) - the one extra check permission
 * resolution carries for a patient, alongside the ordinary can() lookup.
 *
 * Any user holding a permission like clinical_notes.view can otherwise
 * see any patient; this closes that gap only as far as `ward_locations`
 * and `encounters.current_location_id` naturally reach. It protects IPD
 * cleanly and OPD/ER weakly (is_transient consultation bays don't carry
 * the same operational meaning as an inpatient ward) - a real but
 * partial boundary, never represented here or anywhere else as a
 * complete access-control solution.
 */
final readonly class WardScopeService
{
    public function __construct(
        private WardScopeRepositoryInterface $scopes,
        private EncounterRepositoryInterface $encounters,
        private WardLocationRepositoryInterface $wardLocations,
    ) {
    }

    /**
     * Whether $user may see $patientId at all, given their ward scope.
     *
     * No user_role_scopes rows for ANY of this user's grants (including
     * holding no grants at all, which falls back to the hardcoded
     * matrix) means unscoped - sees everyone, unchanged from today. Only
     * once EVERY role grant this user holds is individually scoped does
     * this narrow to "does the patient's active encounter's ward fall in
     * the union of those scopes" - and an active encounter with no
     * resolvable ward (current_location_id IS NULL, or no active
     * encounter at all) is denied, never silently allowed.
     */
    public function isPatientVisible(User $user, int $patientId): bool
    {
        if ($this->scopes->hasUnscopedGrant($user->id)) {
            return true;
        }

        $wardName = $this->activeWardName($patientId);

        if ($wardName === null) {
            return false;
        }

        return in_array($wardName, $this->scopes->wardNamesForUser($user->id), true);
    }

    private function activeWardName(int $patientId): ?string
    {
        foreach ($this->encounters->forPatient($patientId) as $encounter) {
            if (!$encounter->isActive()) {
                continue;
            }

            if ($encounter->currentLocationId === null) {
                return null;
            }

            $location = $this->wardLocations->find($encounter->currentLocationId);

            return $location?->wardName;
        }

        return null;
    }
}
