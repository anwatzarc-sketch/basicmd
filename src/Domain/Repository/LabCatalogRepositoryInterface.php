<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\LabPanel;

interface LabCatalogRepositoryInterface
{
    /**
     * Panels without their analytes - the picker on the requisition and
     * result-entry screens, which needs names and nothing else.
     *
     * @return list<LabPanel>
     */
    public function panels(bool $activeOnly = true): array;

    /**
     * Every panel with its analytes attached, in one pair of queries
     * rather than one query per panel. Backs the master test directory.
     *
     * @return list<LabPanel>
     */
    public function panelsWithParameters(bool $activeOnly = true): array;

    public function findPanel(int $id): ?LabPanel;

    /**
     * By the stable code an order carries. Returns the panel even when it
     * has since been deactivated: an order placed under a retired panel
     * still has to be resulted and printed.
     */
    public function findPanelByCode(string $code): ?LabPanel;

    /** @param array<string, mixed> $data */
    public function createPanel(array $data): int;

    /** @param array<string, mixed> $data */
    public function updatePanel(int $id, array $data): bool;

    public function deletePanel(int $id): bool;

    /**
     * Replace a panel's analyte list wholesale, inside one transaction.
     *
     * Wholesale rather than row-by-row because the editing screen is a
     * grid: the technician reorders, renames and deletes rows together
     * and submits once, and a partial application of that would leave the
     * catalogue in a state the person editing it never saw.
     *
     * @param list<array<string, mixed>> $parameters in display order
     */
    public function replaceParameters(int $panelId, array $parameters): void;
}
