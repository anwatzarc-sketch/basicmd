<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Repository;

use MediCareMini\Domain\Entity\DiagnosticOrder;
use MediCareMini\Domain\Enum\DiagnosticCategory;
use MediCareMini\Domain\Enum\DiagnosticStatus;

interface DiagnosticOrderRepositoryInterface
{
    public function findById(int $id): ?DiagnosticOrder;

    /**
     * By the laboratory's own identifier, which is what is printed on the
     * specimen label - so a technician holding a tube can reach the order
     * without knowing the patient.
     */
    public function findByAccessionNumber(string $accessionNumber): ?DiagnosticOrder;

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

    /**
     * The cross-patient work queue: every order matching the filters,
     * unresulted work first.
     *
     * $category carries the same meaning and the same guarantee as
     * forPatient()'s - it is applied in SQL, so a Lab Technician's scope
     * cannot be widened by a display-layer mistake.
     *
     * @return list<DiagnosticOrder>
     */
    public function queue(
        ?DiagnosticCategory $category = null,
        ?DiagnosticStatus $status = null,
        ?string $search = null,
        int $limit = 100,
    ): array;

    /**
     * Order counts per status, for the queue's filter chips and the
     * sidebar badge - counted in SQL so the total stays true beyond the
     * queue's own LIMIT.
     *
     * @return array<string, int> DiagnosticStatus value => count
     */
    public function statusCounts(?DiagnosticCategory $category = null): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /**
     * Update ONLY the columns that describe an order's progress toward a
     * result - status, the encrypted result payload, the specimen block
     * (type, barcode, collection time, clinical location) and who
     * recorded the result when. Never encounter_id, category, the test
     * itself or its ordering physician.
     *
     * A narrower surface than a generic update() is deliberate: the order
     * (what was asked for, by whom, for what encounter) is a historical
     * fact once placed; only its progress toward a result can change.
     * Collection is progress - a specimen is drawn after the order exists
     * - which is why the specimen columns belong on this side of the line
     * and the test code does not.
     *
     * @param array<string, mixed> $data keys outside the allowed set are ignored
     */
    public function updateProgress(int $id, array $data): bool;

    public function encryptResults(string $plaintext): string;

    public function decryptResults(DiagnosticOrder $order): ?string;
}
