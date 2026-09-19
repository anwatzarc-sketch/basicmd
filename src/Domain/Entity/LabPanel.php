<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

/**
 * An orderable laboratory panel (migration 012).
 *
 * panelCode - not the row id - is what a placed order carries, so
 * renaming "CBC" for the report header never orphans the orders already
 * placed under it.
 *
 * $parameters is populated only by the reads that ask for it
 * (LabCatalogRepositoryInterface::findByCode / allWithParameters); the
 * listing query leaves it empty rather than fetching analytes nobody is
 * about to display.
 */
final readonly class LabPanel
{
    /** @param list<LabPanelParameter> $parameters */
    public function __construct(
        public int $id,
        public string $panelCode,
        public string $panelName,
        public string $department,
        public string $reportTitle,
        public string $specimenType,
        public ?string $methodology,
        public bool $isActive,
        public int $sortOrder,
        public array $parameters = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<LabPanelParameter> $parameters
     */
    public static function fromRow(array $row, array $parameters = []): self
    {
        return new self(
            id:           (int) $row['id'],
            panelCode:    (string) $row['panel_code'],
            panelName:    (string) $row['panel_name'],
            department:   (string) $row['department'],
            reportTitle:  (string) $row['report_title'],
            specimenType: (string) $row['specimen_type'],
            methodology:  isset($row['methodology']) && $row['methodology'] !== null && $row['methodology'] !== ''
                              ? (string) $row['methodology']
                              : null,
            isActive:     (bool) ($row['is_active'] ?? true),
            sortOrder:    (int) ($row['sort_order'] ?? 0),
            parameters:   $parameters,
        );
    }

    /** @param list<LabPanelParameter> $parameters */
    public function withParameters(array $parameters): self
    {
        return new self(
            $this->id,
            $this->panelCode,
            $this->panelName,
            $this->department,
            $this->reportTitle,
            $this->specimenType,
            $this->methodology,
            $this->isActive,
            $this->sortOrder,
            $parameters,
        );
    }

    public function parameterCount(): int
    {
        return count($this->parameters);
    }
}
