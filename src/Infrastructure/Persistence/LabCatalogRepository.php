<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\LabPanel;
use Aster\Domain\Entity\LabPanelParameter;
use Aster\Domain\Repository\LabCatalogRepositoryInterface;

/**
 * The master test directory (migration 012).
 *
 * No Encryptor dependency, unlike its clinical neighbours: nothing in
 * this catalogue is patient data. It describes what the laboratory can
 * measure and what counts as normal, which is the same kind of content
 * as the service catalogue.
 */
final class LabCatalogRepository implements LabCatalogRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<LabPanel> */
    public function panels(bool $activeOnly = true): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM lab_panels'
            . ($activeOnly ? ' WHERE is_active = 1' : '')
            . ' ORDER BY sort_order ASC, panel_name ASC',
        );

        return array_map(static fn (array $row): LabPanel => LabPanel::fromRow($row), $rows);
    }

    /**
     * Two queries, not N+1: every panel, then every parameter, grouped in
     * PHP. The directory renders all of them at once, so fetching
     * analytes per panel would issue a query per row of a page that is
     * one table.
     *
     * @return list<LabPanel>
     */
    public function panelsWithParameters(bool $activeOnly = true): array
    {
        $panels = $this->panels($activeOnly);

        if ($panels === []) {
            return [];
        }

        $grouped = $this->parametersByPanel(array_map(
            static fn (LabPanel $panel): int => $panel->id,
            $panels,
        ));

        return array_map(
            static fn (LabPanel $panel): LabPanel => $panel->withParameters($grouped[$panel->id] ?? []),
            $panels,
        );
    }

    public function findPanel(int $id): ?LabPanel
    {
        $row = $this->db->fetchOne('SELECT * FROM lab_panels WHERE id = :id', ['id' => $id]);

        return $row === null ? null : LabPanel::fromRow($row, $this->parametersFor((int) $row['id']));
    }

    public function findPanelByCode(string $code): ?LabPanel
    {
        // No is_active filter: an order placed under a panel that was
        // retired afterwards still has to be resulted and printed.
        $row = $this->db->fetchOne('SELECT * FROM lab_panels WHERE panel_code = :code', ['code' => $code]);

        return $row === null ? null : LabPanel::fromRow($row, $this->parametersFor((int) $row['id']));
    }

    /** @param array<string, mixed> $data */
    public function createPanel(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO lab_panels (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updatePanel(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($data),
        ));

        $data['id'] = $id;

        return $this->db->execute("UPDATE lab_panels SET {$assignments} WHERE id = :id", $data) > 0;
    }

    public function deletePanel(int $id): bool
    {
        // lab_panel_parameters cascades; placed orders carry panel_code as
        // a plain string and are deliberately NOT foreign-keyed to this
        // table, so deleting a retired panel can never delete clinical
        // history along with it.
        return $this->db->execute('DELETE FROM lab_panels WHERE id = :id', ['id' => $id]) > 0;
    }

    /** @param list<array<string, mixed>> $parameters */
    public function replaceParameters(int $panelId, array $parameters): void
    {
        $this->db->transaction(function (Database $db) use ($panelId, $parameters): void {
            $db->execute('DELETE FROM lab_panel_parameters WHERE panel_id = :pid', ['pid' => $panelId]);

            $order = 0;

            foreach ($parameters as $parameter) {
                $order += 10;

                $db->execute(
                    'INSERT INTO lab_panel_parameters
                        (panel_id, parameter_name, unit, ref_min, ref_max, ref_text, methodology, sort_order, is_active)
                     VALUES (:pid, :name, :unit, :rmin, :rmax, :rtext, :method, :sort, 1)',
                    [
                        'pid'    => $panelId,
                        'name'   => (string) $parameter['parameter_name'],
                        'unit'   => (string) ($parameter['unit'] ?? ''),
                        'rmin'   => $parameter['ref_min'] ?? null,
                        'rmax'   => $parameter['ref_max'] ?? null,
                        'rtext'  => $parameter['ref_text'] ?? null,
                        'method' => (string) ($parameter['methodology'] ?? ''),
                        'sort'   => $order,
                    ],
                );
            }
        });
    }

    /** @return list<LabPanelParameter> */
    private function parametersFor(int $panelId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.*, l.panel_code, l.panel_name, l.department
             FROM lab_panel_parameters p
             JOIN lab_panels l ON l.id = p.panel_id
             WHERE p.panel_id = :pid AND p.is_active = 1
             ORDER BY p.sort_order ASC, p.id ASC',
            ['pid' => $panelId],
        );

        return array_map(LabPanelParameter::fromRow(...), $rows);
    }

    /**
     * @param list<int> $panelIds
     * @return array<int, list<LabPanelParameter>>
     */
    private function parametersByPanel(array $panelIds): array
    {
        [$in, $params] = Database::inClause($panelIds);

        $rows = $this->db->fetchAll(
            'SELECT p.*, l.panel_code, l.panel_name, l.department
             FROM lab_panel_parameters p
             JOIN lab_panels l ON l.id = p.panel_id
             WHERE p.panel_id IN ' . $in . ' AND p.is_active = 1
             ORDER BY p.panel_id ASC, p.sort_order ASC, p.id ASC',
            $params,
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['panel_id']][] = LabPanelParameter::fromRow($row);
        }

        return $grouped;
    }
}
