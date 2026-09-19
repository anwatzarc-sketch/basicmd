<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

/**
 * Audit-trail port for Domain services that must write an audit event as
 * part of their own transaction - EncounterService's OPD-to-IPD upgrade
 * is the reason this exists (FRS ENC-006: the conversion "must produce an
 * audit event", listed as step 12 of the transactional workflow itself,
 * not as a follow-up action a caller remembers to take afterwards).
 *
 * Mirrors AuditLogger::record()'s existing signature exactly, so the
 * concrete Infrastructure class implements this port with no changes to
 * its own code beyond declaring it.
 */
interface AuditLoggerInterface
{
    // Phase II action constants a Domain service can reference without
    // depending on the concrete AuditLogger class. Interface constants are
    // as safe to reference as class constants in PHP, and keep the
    // dependency direction pointing the right way. The equivalent
    // pre-Phase-II constants (APPOINTMENT_CREATED etc.) stay on the
    // concrete AuditLogger class, since only Application/Infrastructure
    // code - never a Domain service - references those today.
    public const string PATIENT_REGISTERED  = 'patient.registered';
    public const string ENCOUNTER_STARTED   = 'encounter.started';
    public const string ENCOUNTER_ADMITTED  = 'encounter.admitted_ipd';
    public const string ENCOUNTER_DISCHARGED = 'encounter.discharged';
    public const string ENCOUNTER_WALK_OUT   = 'encounter.walk_out';

    /** @param array<string, mixed>|null $changes */
    public function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $summary = null,
        ?array $changes = null,
    ): void;
}
