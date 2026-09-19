<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Support;

use MediCareMini\Domain\Entity\User;
use MediCareMini\Domain\Enum\ClinicalNoteType;
use MediCareMini\Domain\Enum\DiagnosticCategory;

/**
 * The single visibility/capability map behind the Patient Detail shell
 * (spec §3/§4). One route, one component tree - what a signed-in user
 * sees and can do on it is entirely a function of which permission
 * slugs they hold, resolved here in one place, rather than six
 * hardcoded per-role branches scattered across the controller and
 * views. Every method takes the real permission set from
 * User::resolvePermissions() (roles/permissions/role_permissions -
 * never users.role, the enum, which is a synced cache and never
 * consulted directly here or anywhere this class is used).
 *
 * A tab or action this class says "no" to is not merely hidden by CSS -
 * PatientController never fetches that tab's data for a user who can't
 * see it (mirrors DashboardController's own documented rule), so the
 * information is never present in the HTML source either.
 */
final class PatientDetailAccess
{
    /**
     * Every tab a role could ever see, in display order. 'overview' is
     * omitted deliberately - reaching this page at all already required
     * patients.view (the route's own can: gate), so there is no separate
     * permission that would ever hide the one tab everyone who can open
     * the page sees.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function visibleTabs(User $user): array
    {
        $tabs = [['key' => 'overview', 'label' => 'Overview']];

        if ($user->can('encounters.view')) {
            $tabs[] = ['key' => 'encounters', 'label' => 'Encounters'];
        }

        if ($user->can('clinical_notes.view')) {
            $tabs[] = ['key' => 'notes', 'label' => 'Clinical Notes'];
        }

        if ($user->can('diagnostics.view')) {
            $tabs[] = ['key' => 'diagnostics', 'label' => 'Diagnostic Orders'];
        }

        if ($user->can('prescriptions.view')) {
            $tabs[] = ['key' => 'prescriptions', 'label' => 'Prescriptions'];
        }

        if ($user->can('billing.view')) {
            $tabs[] = ['key' => 'financial', 'label' => 'Financial Ledger'];
        }

        if ($user->can('audit.view') || $user->can('audit.view_own')) {
            $tabs[] = ['key' => 'audit', 'label' => 'Audit'];
        }

        return $tabs;
    }

    public static function canViewTab(User $user, string $tab): bool
    {
        if ($tab === 'overview') {
            return true;
        }

        foreach (self::visibleTabs($user) as $entry) {
            if ($entry['key'] === $tab) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------
    //  Overview
    // -----------------------------------------------------------------

    public static function canEditDemographics(User $user): bool
    {
        return $user->can('patients.write');
    }

    public static function canProvisionPortalAccess(User $user): bool
    {
        return $user->can('patients.write');
    }

    /**
     * Accountant's Overview shows identity only, per spec §4.4 - no
     * clinical fields (allergies included) on a role that has no
     * clinical_notes/diagnostics/prescriptions permission at all. This
     * is the one place that distinction has to be made explicitly,
     * because every other role that can reach this page holds at least
     * one clinical permission.
     */
    public static function canSeeClinicalSummary(User $user): bool
    {
        return $user->can('clinical_notes.view')
            || $user->can('diagnostics.view')
            || $user->can('prescriptions.view');
    }

    // -----------------------------------------------------------------
    //  Clinical notes - the narrow/broad pair decides note_type, never
    //  a comparison against users.role.
    // -----------------------------------------------------------------

    public static function canWriteNotes(User $user): bool
    {
        return $user->can('clinical_notes.write') || $user->can('clinical_notes.write_vitals');
    }

    /**
     * Which note_type values this user may create right now. Empty for
     * a view-only role - the create form/action simply does not render
     * rather than rendering disabled, the same progressive-disclosure
     * principle the rest of this app's permission-gated UI already
     * follows.
     *
     * @return list<ClinicalNoteType>
     */
    public static function allowedNoteTypes(User $user): array
    {
        if ($user->can('clinical_notes.write')) {
            return ClinicalNoteType::all();
        }

        if ($user->can('clinical_notes.write_vitals')) {
            return [ClinicalNoteType::VITALS, ClinicalNoteType::TRIAGE];
        }

        return [];
    }

    public static function canWriteNoteType(User $user, ClinicalNoteType $type): bool
    {
        return in_array($type, self::allowedNoteTypes($user), true);
    }

    // -----------------------------------------------------------------
    //  Diagnostic orders - result_lab is the Lab Technician's whole
    //  relationship with this panel: it scopes what they can both see
    //  and act on to category = Lab, per spec §4.5's own reasoning
    //  ("lab_technician" names the role, nothing represents Imaging/PACS).
    // -----------------------------------------------------------------

    public static function canOrderDiagnostics(User $user): bool
    {
        return $user->can('diagnostics.order');
    }

    public static function canRecordResult(User $user, DiagnosticCategory $category): bool
    {
        if ($user->can('diagnostics.result')) {
            return true;
        }

        return $category === DiagnosticCategory::LAB && $user->can('diagnostics.result_lab');
    }

    /**
     * The one category this user's diagnostics view is confined to, or
     * null for unrestricted (every broad-grant role, and every
     * view-only role that holds no result-recording right at all - a
     * nurse reading orders for ward context sees every category, it is
     * only the acting-on-results right that is ever narrowed).
     */
    public static function diagnosticCategoryScope(User $user): ?DiagnosticCategory
    {
        if ($user->can('diagnostics.order') || $user->can('diagnostics.result')) {
            return null;
        }

        if ($user->can('diagnostics.result_lab')) {
            return DiagnosticCategory::LAB;
        }

        return null;
    }

    // -----------------------------------------------------------------
    //  Prescriptions
    // -----------------------------------------------------------------

    public static function canWritePrescriptions(User $user): bool
    {
        return $user->can('prescriptions.write');
    }

    /**
     * The bare is_dispensed toggle - spec §6's own resolution (a) for
     * the pharmacist-shaped gap: no dispensed_by/at columns, no
     * pharmacist role, physician and nurse are the closest real fit
     * until one exists.
     */
    public static function canDispense(User $user): bool
    {
        return $user->can('prescriptions.dispense');
    }

    // -----------------------------------------------------------------
    //  Financial ledger - writes are never inline on this page (see the
    //  Financial panel's own docblock); this only gates what's shown.
    // -----------------------------------------------------------------

    public static function canViewFinancials(User $user): bool
    {
        return $user->can('billing.view');
    }

    // -----------------------------------------------------------------
    //  Audit - audit.view sees every entry; audit.view_own is scoped to
    //  the signed-in user's own actor_user_id at the query itself
    //  (AuditLogger::forPatient()'s $actorUserId parameter), never as a
    //  display-layer filter over the full trail.
    // -----------------------------------------------------------------

    public static function auditScopedToOwnEntriesOnly(User $user): bool
    {
        return !$user->can('audit.view') && $user->can('audit.view_own');
    }
}
