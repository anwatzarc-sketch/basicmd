<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\DTO\LabReport;
use Aster\Domain\DTO\LabResultLine;
use Aster\Domain\Entity\DiagnosticOrder;
use Aster\Domain\Entity\LabPanel;
use Aster\Domain\Repository\DiagnosticOrderRepositoryInterface;
use Aster\Domain\Repository\LabCatalogRepositoryInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\ValueObject\AccessionNumber;
use DateTimeImmutable;

/**
 * The one boundary where a lab result crosses between ciphertext and a
 * usable object.
 *
 * Everything above this class - controllers, templates - works with a
 * LabReport; everything below it works with
 * diagnostic_orders.results_payload_encrypted. Nothing else calls
 * decryptResults() for a Lab order, which is the same discipline
 * PatientController applies to clinical notes, moved into a service here
 * because three screens need it rather than one.
 */
final class LabReportService
{
    public function __construct(
        private readonly DiagnosticOrderRepositoryInterface $orders,
        private readonly LabCatalogRepositoryInterface $catalog,
        private readonly NumberSequenceInterface $sequence,
    ) {
    }

    /**
     * The saved report for an order, or null when nothing has been
     * recorded yet.
     */
    public function saved(DiagnosticOrder $order): ?LabReport
    {
        if (!$order->hasResults()) {
            return null;
        }

        $plaintext = $this->orders->decryptResults($order);

        if ($plaintext === null || trim($plaintext) === '') {
            return null;
        }

        return LabReport::decode($plaintext, $order->testName);
    }

    /**
     * What the entry grid opens with: the saved report if there is one,
     * otherwise a blank matrix built from the order's panel, otherwise a
     * single empty line named after the test itself.
     *
     * The saved report wins even when the catalogue has since gained a
     * parameter - re-deriving the grid from today's catalogue would
     * silently drop a value already keyed in against a parameter that was
     * removed, and quietly losing a result is worse than showing a grid
     * that no longer matches the panel.
     */
    public function workingCopy(DiagnosticOrder $order): LabReport
    {
        $saved = $this->saved($order);

        if ($saved !== null && $saved->isStructured()) {
            return $saved;
        }

        $panel = $this->panelFor($order);
        $blank = $this->blank($order, $panel);

        // A legacy free-text result keeps its written impression and
        // gains the structured grid beneath it, rather than being
        // discarded on first edit.
        return $saved === null ? $blank : new LabReport(
            $blank->title,
            $blank->panelCode,
            $blank->lines,
            $saved->impression,
        );
    }

    /** An empty report for this order, pre-populated from its panel. */
    public function blank(DiagnosticOrder $order, ?LabPanel $panel = null): LabReport
    {
        $panel ??= $this->panelFor($order);

        if ($panel === null) {
            return new LabReport(
                title:      $order->testName,
                panelCode:  $order->panelCode,
                lines:      [new LabResultLine($order->testName, '', '', null, null, null, '')],
                impression: '',
            );
        }

        return new LabReport(
            title:      $panel->reportTitle,
            panelCode:  $panel->panelCode,
            lines:      array_map(
                static fn ($parameter): LabResultLine => LabResultLine::fromParameter($parameter),
                $panel->parameters,
            ),
            impression: '',
        );
    }

    /** The catalogue panel an order was placed under, if it still exists. */
    public function panelFor(DiagnosticOrder $order): ?LabPanel
    {
        return $order->panelCode !== null ? $this->catalog->findPanelByCode($order->panelCode) : null;
    }

    /**
     * Assemble a report from the submitted entry grid.
     *
     * $rows is the raw `rows` member of the request body: one associative
     * array per grid row (`rows[3][name]`, `rows[3][value]`, …), NOT a set
     * of parallel `name[]` / `value[]` arrays. That shape is deliberate
     * and is the reason this takes untyped input rather than seven typed
     * lists. Parallel arrays only line up while every one of them keeps
     * every element; a single dropped member shifts the rest, and the
     * failure mode of a shifted lab grid is a haemoglobin value printed
     * against a platelet count's reference range. Keeping each row whole
     * makes that misalignment unrepresentable.
     *
     * Rows are renumbered by iteration order, so display order follows
     * the order the fields appear in the form rather than the indices the
     * browser happened to send. A row with a blank parameter name is
     * dropped: that is an empty line somebody added and never filled, not
     * a parameter called "".
     *
     * @param mixed $rows the request body's `rows` member, of any shape
     */
    public function compose(string $title, ?string $panelCode, string $impression, mixed $rows): LabReport
    {
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = self::scalar($row['name'] ?? null);

            if ($name === '') {
                continue;
            }

            $lines[] = new LabResultLine(
                parameterName: $name,
                value:         self::scalar($row['value'] ?? null),
                unit:          self::scalar($row['unit'] ?? null),
                referenceMin:  self::numeric(self::scalar($row['min'] ?? null)),
                referenceMax:  self::numeric(self::scalar($row['max'] ?? null)),
                referenceText: self::text(self::scalar($row['text'] ?? null)),
                methodology:   self::scalar($row['method'] ?? null),
            );
        }

        return new LabReport(trim($title), $panelCode, $lines, trim($impression));
    }

    /** Ciphertext for the results column. */
    public function encode(LabReport $report): string
    {
        return $this->orders->encryptResults($report->encode());
    }

    /**
     * Issue the next accession number for a date.
     *
     * Straight through NumberSequence, which is atomic - see
     * VisitNumber's docblock for why generate-and-check is not used for
     * identifiers that must be sequential per day.
     */
    public function issueAccessionNumber(?DateTimeImmutable $on = null): AccessionNumber
    {
        $on ??= new DateTimeImmutable();

        return AccessionNumber::forDate($on, $this->sequence->next(AccessionNumber::scopeFor($on)));
    }

    /** A trimmed string from untrusted input; anything non-scalar is ''. */
    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function numeric(string $raw): ?float
    {
        return $raw !== '' && is_numeric($raw) ? (float) $raw : null;
    }

    private static function text(string $raw): ?string
    {
        return $raw !== '' ? $raw : null;
    }
}
