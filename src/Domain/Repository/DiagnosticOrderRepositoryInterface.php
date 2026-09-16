<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\DiagnosticOrder;
use Aster\Domain\Enum\DiagnosticCategory;

interface DiagnosticOrderRepositoryInterface
{
    public function findById(int $id): ?DiagnosticOrder;

    /** @return list<DiagnosticOrder> */
    public function forEncounter(int $encounterId): array;

    /**
     * Every diagnostic order across a patient's whole encounter history,
     * most recent first - the portal dashboard's "diagnostic summaries"
     * (FRS 10.6). Joins through encounters rather than the caller fetching
     * per-encounter and merging in PHP: the dashboard scopes strictly to
     * one patient_id, so the join is both simpler and the only way to
     * enforce that scope in SQL rather than trust every caller to filter
     * correctly afterwards.
     *
     * $category, when given, scopes to one category only - how Lab
     * Technician's queue enforces "Lab orders only" (diagnostics.result_lab)
     * at the query itself, not by filtering an already-fetched list in
     * PHP where a bug could leak an Imaging/PACS row into view.
     *
     * @return list<DiagnosticOrder>
     */
    public function forPatient(int $patientId, int $limit = 50, ?DiagnosticCategory $category = null): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /**
     * Update ONLY status and/or results - never encounter_id, category, or
     * any of the original order details. A narrower surface than a
     * generic update() is deliberate: the order itself (what was asked
     * for, by whom, for what encounter) is a historical fact once placed;
     * only its progress toward a result can change.
     *
     * @param array{status?: string, results_payload_encrypted?: ?string} $data
     */
    public function updateProgress(int $id, array $data): bool;

    public function encryptResults(string $plaintext): string;

    public function decryptResults(DiagnosticOrder $order): ?string;
}
