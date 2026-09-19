<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\Service\LabReportService;
use Aster\Domain\Entity\DiagnosticOrder;
use Aster\Domain\Entity\Encounter;
use Aster\Domain\Entity\User;
use Aster\Domain\Enum\DiagnosticCategory;
use Aster\Domain\Enum\DiagnosticStatus;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Repository\DiagnosticOrderRepositoryInterface;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LabCatalogRepositoryInterface;
use Aster\Domain\Services\WardScopeService;
use Aster\Domain\ValueObject\VisitNumber;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\Support\PatientDetailAccess;
use Aster\Presentation\View\View;
use DateTimeImmutable;
use PDOException;

/**
 * The laboratory module: work queue, requisition, structured result entry,
 * the printed report sheet, and the master test directory.
 *
 * Every screen here is a LAB-ONLY view of `diagnostic_orders`. The
 * category filter is pinned to DiagnosticCategory::LAB at the query
 * (DiagnosticOrderRepositoryInterface::queue()'s own guarantee), not by
 * filtering a wider list afterwards - so this module can never show a Lab
 * Technician an Imaging order, and equally never shows a physician one
 * here when they came looking for bloods.
 *
 * Authorisation reuses PatientDetailAccess rather than growing a second
 * map: ordering is diagnostics.order, recording a result is
 * canRecordResult() against the order's OWN category (which is what makes
 * diagnostics.result_lab sufficient here and insufficient on an Imaging
 * order), and reading is the route's own can:diagnostics.view gate. Ward
 * scope is applied on top, per patient, exactly as the Patient Detail
 * screens apply it.
 */
final class LabController extends Controller
{
    /** Orders shown in one queue page. */
    private const int QUEUE_LIMIT = 200;

    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly DiagnosticOrderRepositoryInterface $orders,
        private readonly LabCatalogRepositoryInterface $catalog,
        private readonly EncounterRepositoryInterface $encounters,
        private readonly LabReportService $reports,
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
        private readonly WardScopeService $wardScope,
    ) {
        parent::__construct($view, $session, $config);
    }

    // -----------------------------------------------------------------
    //  Work queue
    // -----------------------------------------------------------------

    public function queue(Request $request): Response
    {
        $user   = $this->requireUser();
        $status = DiagnosticStatus::tryFrom($request->string('status'));
        $search = $request->input('q');

        $orders = $this->visibleOnly($user, $this->orders->queue(
            DiagnosticCategory::LAB,
            $status,
            $search,
            self::QUEUE_LIMIT,
        ));

        return $this->renderAdmin('admin/lab/queue', [
            'orders'       => $orders,
            'statuses'     => DiagnosticStatus::all(),
            'activeStatus' => $status,
            'searchTerm'   => $search ?? '',
            'counts'       => $this->orders->statusCounts(DiagnosticCategory::LAB),
            'canOrder'     => PatientDetailAccess::canOrderDiagnostics($user),
            'canResult'    => PatientDetailAccess::canRecordResult($user, DiagnosticCategory::LAB),
            'meta'         => ['title' => 'Laboratory', 'noindex' => true],
        ]);
    }

    // -----------------------------------------------------------------
    //  Requisition
    // -----------------------------------------------------------------

    /**
     * A requisition is raised against an ACTIVE ENCOUNTER, never against
     * a patient directly. That is not a UI preference: diagnostic_orders
     * hangs off encounters (migration 004), because "which visit was this
     * drawn during" is the question every downstream reader - billing,
     * the discharge summary, the patient portal - actually asks.
     */
    public function requisitionForm(Request $request): Response
    {
        $user      = $this->requireUser();
        $encounter = $this->encounterFromRequest($request);

        if ($encounter !== null && !$this->wardScope->isPatientVisible($user, $encounter->patientId)) {
            throw HttpException::forbidden();
        }

        return $this->renderAdmin('admin/lab/requisition-form', [
            'encounter'  => $encounter,
            'notFound'   => $request->input('visit') !== null && $encounter === null,
            'searchTerm' => $request->string('visit'),
            'active'     => $this->visibleEncounters($user),
            'panels'     => $this->catalog->panels(),
            'meta'       => ['title' => 'New Lab Requisition', 'noindex' => true],
        ]);
    }

    public function saveRequisition(Request $request): Response
    {
        $user = $this->requireUser();
        $back = $this->config->adminPath . '/lab/requisitions/create';

        $encounterId = $request->nullableInt('encounter_id');
        $encounter   = $encounterId !== null ? $this->encounters->findById($encounterId) : null;

        if ($encounter === null) {
            return $this->redirectWithError($back, 'Choose an active encounter to raise this requisition against.');
        }

        if (!$this->wardScope->isPatientVisible($user, $encounter->patientId)) {
            throw HttpException::forbidden();
        }

        $panel = $this->catalog->findPanelByCode($request->string('panel_code'));

        if ($panel === null) {
            return $this->redirectWithError($back, 'Choose a panel from the test directory.');
        }

        $icdCode = $request->string('icd_code');

        if ($icdCode === '') {
            return $this->redirectWithError($back, 'An ICD code is required on every diagnostic order.');
        }

        $accession = $this->reports->issueAccessionNumber();

        $orderId = $this->orders->create([
            'encounter_id'          => $encounter->id,
            'ordering_physician_id' => $user->id,
            'category'              => DiagnosticCategory::LAB->value,
            'test_code'             => $panel->panelCode,
            'test_name'             => $panel->panelName,
            'icd_code'              => $icdCode,
            'status'                => DiagnosticStatus::ORDERED->value,
            'accession_number'      => $accession->value,
            'panel_code'            => $panel->panelCode,
            'specimen_type'         => $request->string('specimen_type', $panel->specimenType),
            // The tube carries the accession number unless the laboratory
            // uses its own pre-printed labels, in which case the
            // technician overwrites it at result entry.
            'specimen_barcode'      => $accession->value,
            'clinical_location'     => $request->input('clinical_location'),
        ]);

        $this->audit->record(
            AuditLogger::DIAGNOSTIC_ORDER_CREATED,
            'encounter',
            $encounter->id,
            sprintf('Lab requisition %s raised: %s (%s)', $accession->value, $panel->panelName, $encounter->patientVisitNumber->value),
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/lab/orders/' . $orderId . '/results',
            sprintf('Requisition %s raised. Specimen label: %s.', $accession->value, $accession->value),
        );
    }

    // -----------------------------------------------------------------
    //  Result entry
    // -----------------------------------------------------------------

    public function resultEntry(Request $request): Response
    {
        $user  = $this->requireUser();
        $order = $this->labOrder($request, $user);

        if (!PatientDetailAccess::canRecordResult($user, $order->category)) {
            throw HttpException::forbidden();
        }

        if ($order->status === DiagnosticStatus::CANCELLED) {
            return $this->redirectWithError(
                $this->config->adminPath . '/lab',
                'That requisition was cancelled; results cannot be recorded against it.',
            );
        }

        return $this->renderAdmin('admin/lab/result-entry', [
            'order'    => $order,
            'report'   => $this->reports->workingCopy($order),
            'panel'    => $this->reports->panelFor($order),
            'panels'   => $this->catalog->panels(),
            'released' => $order->status === DiagnosticStatus::COMPLETED,
            'meta'     => ['title' => 'Record Lab Results', 'noindex' => true],
        ]);
    }

    public function saveResults(Request $request): Response
    {
        $user  = $this->requireUser();
        $order = $this->labOrder($request, $user);
        $back  = $this->config->adminPath . '/lab/orders/' . $order->id . '/results';

        if (!PatientDetailAccess::canRecordResult($user, $order->category)) {
            throw HttpException::forbidden();
        }

        // "Release" means the report may be printed and handed over;
        // "draft" parks the work in progress. Both are intents, resolved
        // against the real transition machine below rather than written
        // as a status the form chose.
        $release = $request->string('action') === 'release';
        $target  = $release ? DiagnosticStatus::COMPLETED : DiagnosticStatus::IN_PROGRESS;

        if (!$order->status->canReach($target)) {
            return $this->redirectWithError($back, sprintf(
                'A %s requisition cannot be moved to %s.',
                $order->status->labLabel(),
                $target->labLabel(),
            ));
        }

        $report = $this->reports->compose(
            $request->string('report_title', $order->testName),
            $order->panelCode,
            $request->string('impression'),
            $request->body['rows'] ?? null,
        );

        if ($release && $report->reportedLines() === [] && $report->impression === '') {
            return $this->redirectWithError($back, 'Enter at least one result value or a written impression before releasing.');
        }

        $now = new DateTimeImmutable();

        $progress = [
            'status'                    => $target->value,
            'results_payload_encrypted' => $this->reports->encode($report),
            'specimen_type'             => $request->input('specimen_type'),
            'specimen_barcode'          => $request->input('specimen_barcode'),
            'clinical_location'         => $request->input('clinical_location'),
            'collected_at'              => $this->dateTimeInput($request, 'collected_at') ?? $order->collectedAt?->format('Y-m-d H:i:s'),
            'resulted_by_id'            => $user->id,
            'resulted_at'               => $now->format('Y-m-d H:i:s'),
        ];

        try {
            $this->orders->updateProgress($order->id, $progress);
        } catch (PDOException $e) {
            // specimen_barcode is UNIQUE, because two tubes carrying the
            // same label is precisely the mix-up a barcode exists to stop.
            // Rethrowing anything else: only the collision is a message
            // the technician can act on.
            if (!Database::isDuplicateKey($e)) {
                throw $e;
            }

            return $this->redirectWithError($back, sprintf(
                'Specimen label %s is already on another requisition. Results were not saved.',
                $progress['specimen_barcode'] ?? '',
            ));
        }

        $this->audit->record(
            AuditLogger::DIAGNOSTIC_RESULT_RECORDED,
            'encounter',
            $order->encounterId,
            sprintf(
                '%s %s: %d parameter(s), %d abnormal, moved to %s',
                $order->accessionNumber?->value ?? ('Order #' . $order->id),
                $release ? 'released' : 'saved as draft',
                count($report->reportedLines()),
                $report->abnormalCount(),
                $target->label(),
            ),
        );

        if (!$release) {
            return $this->redirectWithSuccess($back, 'Draft saved. Nothing has been released for printing yet.');
        }

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/lab/orders/' . $order->id . '/report',
            'Results verified and released. The report sheet is ready to print.',
        );
    }

    // -----------------------------------------------------------------
    //  Printed report
    // -----------------------------------------------------------------

    /**
     * The A4 result sheet, in its own layout with no admin chrome.
     *
     * The view is audited the same way a payment proof is (FRS's stated
     * stance on reading a stored sensitive document): this is the screen
     * where a patient's numbers are rendered in full, and "who read this
     * result, and when" is worth more in an audit trail than the row is
     * worth in storage.
     */
    public function report(Request $request): Response
    {
        $user  = $this->requireUser();
        $order = $this->labOrder($request, $user);

        $this->audit->record(
            AuditLogger::LAB_REPORT_VIEWED,
            'encounter',
            $order->encounterId,
            sprintf('Lab report opened: %s (%s)', $order->accessionNumber?->value ?? ('#' . $order->id), $order->testName),
        );

        return $this->render('admin/lab/report', [
            'order'       => $order,
            'report'      => $this->reports->saved($order),
            'settings'    => $this->settings,
            'generatedAt' => new DateTimeImmutable(),
            'canResult'   => PatientDetailAccess::canRecordResult($user, $order->category),
            'meta'        => ['title' => 'Lab Report', 'noindex' => true],
        ], 'layouts/print');
    }

    // -----------------------------------------------------------------
    //  Master test directory
    // -----------------------------------------------------------------

    public function catalog(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->renderAdmin('admin/lab/catalog', [
            'panels'    => $this->catalog->panelsWithParameters(false),
            'canManage' => $user->can('lab_catalog.manage'),
            'meta'      => ['title' => 'Test Directory', 'noindex' => true],
        ]);
    }

    public function panelForm(Request $request): Response
    {
        $id    = $request->routeInt('id');
        $panel = $id > 0 ? $this->catalog->findPanel($id) : null;

        if ($id > 0 && $panel === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/lab/panel-form', [
            'panel' => $panel,
            'meta'  => ['title' => $panel === null ? 'New Panel' : 'Edit ' . $panel->panelName, 'noindex' => true],
        ]);
    }

    public function savePanel(Request $request): Response
    {
        $id    = $request->routeInt('id');
        $panel = $id > 0 ? $this->catalog->findPanel($id) : null;
        $back  = $this->config->adminPath . '/lab/catalog' . ($id > 0 ? '/' . $id . '/edit' : '/create');

        if ($id > 0 && $panel === null) {
            throw HttpException::notFound();
        }

        $code = mb_strtoupper($request->string('panel_code'));
        $name = $request->string('panel_name');

        if ($code === '' || $name === '' || preg_match('/^[A-Z0-9_-]{2,32}$/', $code) !== 1) {
            return $this->redirectWithError($back, 'A panel needs a name and a short code (2-32 letters, digits, dash or underscore).');
        }

        $existing = $this->catalog->findPanelByCode($code);

        if ($existing !== null && $existing->id !== $panel?->id) {
            return $this->redirectWithError($back, sprintf('The code %s is already used by %s.', $code, $existing->panelName));
        }

        $data = [
            'panel_code'    => $code,
            'panel_name'    => $name,
            'department'    => $request->string('department', 'General'),
            'report_title'  => $request->string('report_title', $name),
            'specimen_type' => $request->string('specimen_type', 'Not specified'),
            'methodology'   => $request->input('methodology'),
            'is_active'     => $request->bool('is_active') ? 1 : 0,
            'sort_order'    => $request->int('sort_order'),
        ];

        $panelId = $panel?->id ?? 0;

        if ($panel === null) {
            $panelId = $this->catalog->createPanel($data);
        } else {
            $this->catalog->updatePanel($panel->id, $data);
        }

        $this->catalog->replaceParameters($panelId, $this->parameterRows($request));

        $this->audit->record(
            $panel === null ? AuditLogger::CONTENT_CREATED : AuditLogger::CONTENT_UPDATED,
            'lab_panel',
            $panelId,
            sprintf('Test panel %s (%s) saved', $name, $code),
        );

        return $this->redirectWithSuccess($this->config->adminPath . '/lab/catalog', sprintf('Panel %s saved.', $name));
    }

    public function deletePanel(Request $request): Response
    {
        $panel = $this->catalog->findPanel($request->routeInt('id'));

        if ($panel === null) {
            throw HttpException::notFound();
        }

        $this->catalog->deletePanel($panel->id);

        $this->audit->record(
            AuditLogger::CONTENT_DELETED,
            'lab_panel',
            $panel->id,
            sprintf('Test panel %s (%s) deleted', $panel->panelName, $panel->panelCode),
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/lab/catalog',
            sprintf('Panel %s deleted. Requisitions already placed under it are untouched.', $panel->panelName),
        );
    }

    /**
     * The analytes of one panel as JSON, so the entry grid can swap in a
     * preset without a page reload.
     *
     * Catalogue content only - no patient data crosses this endpoint,
     * which is why it is gated on diagnostics.view rather than on the
     * result-recording right.
     */
    public function panelParameters(Request $request): Response
    {
        $panel = $this->catalog->findPanelByCode($request->routeString('code'));

        if ($panel === null) {
            return Response::json(['ok' => false, 'message' => 'Unknown panel.'], 404);
        }

        return Response::json([
            'ok'    => true,
            'code'  => $panel->panelCode,
            'title' => $panel->reportTitle,
            'rows'  => array_map(static fn ($parameter): array => [
                'name'   => $parameter->parameterName,
                'value'  => '',
                'unit'   => $parameter->unit,
                'min'    => $parameter->referenceMin,
                'max'    => $parameter->referenceMax,
                'text'   => $parameter->referenceText,
                'method' => $parameter->methodology,
            ], $panel->parameters),
        ]);
    }

    // -----------------------------------------------------------------
    //  Shared helpers
    // -----------------------------------------------------------------

    /**
     * Load the routed order, refusing anything that is not a Lab order or
     * whose patient is outside this user's ward scope.
     *
     * The category check is not redundant with the queue's SQL filter: a
     * user can type an order id into the URL, and an Imaging order opened
     * on a laboratory screen would be resulted by whoever holds
     * diagnostics.result_lab - exactly the scope escape that permission
     * exists to prevent.
     */
    private function labOrder(Request $request, User $user): DiagnosticOrder
    {
        $order = $this->orders->findById($request->routeInt('id'));

        if ($order === null || !$order->isLab()) {
            throw HttpException::notFound();
        }

        if ($order->patientId === null || !$this->wardScope->isPatientVisible($user, $order->patientId)) {
            throw HttpException::forbidden();
        }

        return $order;
    }

    /**
     * Ward scope applied after the query, as PatientController::index()
     * does for search - the check spans encounters/ward_locations rather
     * than diagnostic_orders, so it does not reduce to a WHERE clause on
     * the queue's own table.
     *
     * @param list<DiagnosticOrder> $orders
     * @return list<DiagnosticOrder>
     */
    private function visibleOnly(User $user, array $orders): array
    {
        $decisions = [];

        return array_values(array_filter($orders, function (DiagnosticOrder $order) use ($user, &$decisions): bool {
            if ($order->patientId === null) {
                return false;
            }

            // A queue page holds many orders for few patients; deciding
            // once per patient keeps this from issuing the ward lookup
            // dozens of times for the same person.
            return $decisions[$order->patientId] ??= $this->wardScope->isPatientVisible($user, $order->patientId);
        }));
    }

    /** @return list<Encounter> */
    private function visibleEncounters(User $user): array
    {
        return array_values(array_filter(
            $this->encounters->active(),
            fn (Encounter $encounter): bool
                => $this->wardScope->isPatientVisible($user, $encounter->patientId),
        ));
    }

    private function encounterFromRequest(Request $request): ?Encounter
    {
        $visit = $request->input('visit');

        if ($visit === null) {
            return null;
        }

        $visitNumber = VisitNumber::tryFrom($visit);

        return $visitNumber !== null ? $this->encounters->findByVisitNumber($visitNumber) : null;
    }

    /**
     * A `datetime-local` value as a MySQL DATETIME, or null.
     *
     * Parsed rather than passed through: the browser sends
     * "2026-09-19T08:30" and MySQL wants "2026-09-19 08:30:00", and
     * anything that is not a real timestamp becomes null instead of a
     * string the column would silently coerce to zeroes.
     */
    private function dateTimeInput(Request $request, string $key): ?string
    {
        $raw = $request->input($key);

        if ($raw === null) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);

        return $parsed === false ? null : $parsed->format('Y-m-d H:i:s');
    }

    /**
     * The catalogue editing grid's rows, in submitted order.
     *
     * Same whole-row shape as the result grid, for the same reason - see
     * LabReportService::compose()'s docblock on why parallel arrays are
     * not used for anything that carries a reference interval.
     *
     * Repeated parameter names are collapsed to the first occurrence.
     * (panel_id, parameter_name) is UNIQUE, so a duplicate would abort the
     * whole save on a constraint violation after the delete half of
     * replaceParameters() had already run - losing the panel rather than
     * rejecting one row. Keeping the first is the same choice a paper
     * form's reader would make.
     *
     * @return list<array<string, mixed>>
     */
    private function parameterRows(Request $request): array
    {
        $rows   = $request->body['rows'] ?? null;
        $parsed = [];
        $seen   = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = is_scalar($row['name'] ?? null) ? trim((string) $row['name']) : '';
            $key  = mb_strtolower($name);

            if ($name === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $min = is_scalar($row['min'] ?? null) ? trim((string) $row['min']) : '';
            $max = is_scalar($row['max'] ?? null) ? trim((string) $row['max']) : '';

            $parsed[] = [
                'parameter_name' => $name,
                'unit'           => is_scalar($row['unit'] ?? null) ? trim((string) $row['unit']) : '',
                'ref_min'        => is_numeric($min) ? $min : null,
                'ref_max'        => is_numeric($max) ? $max : null,
                'ref_text'       => is_scalar($row['text'] ?? null) && trim((string) $row['text']) !== ''
                                        ? trim((string) $row['text'])
                                        : null,
                'methodology'    => is_scalar($row['method'] ?? null) ? trim((string) $row['method']) : '',
            ];
        }

        return $parsed;
    }
}
