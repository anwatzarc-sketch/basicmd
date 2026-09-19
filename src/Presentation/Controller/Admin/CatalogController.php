<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\FacilityStatus;
use Aster\Domain\Enum\ServiceCategory;
use Aster\Domain\Exception\HttpException;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\FacilityRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Storage\FileUploader;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * CMS for the three simple catalogue resources: services, health packages and
 * facilities.
 *
 * Grouped into one controller because all three are the same shape - a
 * bilingual title and description, a sort order, an active flag - and three
 * near-identical classes would be harder to keep consistent than one file
 * with three clearly separated sections.
 */
final class CatalogController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly ServiceRepository $services,
        private readonly PackageRepository $packages,
        private readonly FacilityRepository $facilities,
        private readonly FileUploader $uploader,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    // =================================================================
    //  Services
    // =================================================================

    public function services(Request $request): Response
    {
        return $this->renderAdmin('admin/catalog/services', [
            'services'   => $this->services->adminList($request->input('q'), $request->input('status')),
            'categories' => ServiceCategory::all(),
            'filters'    => ['q' => $request->input('q'), 'status' => $request->input('status')],
            'meta'       => ['title' => 'Services', 'noindex' => true],
        ]);
    }

    public function serviceForm(Request $request): Response
    {
        $id      = $request->routeInt('id');
        $service = $id > 0 ? $this->services->findById($id) : null;

        if ($id > 0 && $service === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/catalog/service-form', [
            'service'    => $service,
            'categories' => ServiceCategory::all(),
            'meta'       => [
                'title'   => $service === null ? 'Add Service' : 'Edit ' . $service->name,
                'noindex' => true,
            ],
        ]);
    }

    public function saveService(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $name = $request->string('ser_name');

        if ($name === '') {
            return $this->redirectWithError(
                $this->config->adminPath . '/services',
                'The service name is required.',
            );
        }

        $data = [
            'icon'           => mb_substr($request->string('icon', ''), 0, 16),
            'ser_name'       => $name,
            'name_am'        => $request->input('name_am'),
            'name_om'        => $request->input('name_om'),
            'description'    => $request->input('description'),
            'description_am' => $request->input('description_am'),
            'description_om' => $request->input('description_om'),
            'category'       => (ServiceCategory::tryFrom($request->string('category')) ?? ServiceCategory::CLINICAL)->value,
            'price'          => number_format(max(0, $request->float('price')), 2, '.', ''),
            'duration_min'   => max(5, min(480, $request->int('duration_min', 30))),
            'is_featured'    => $request->bool('is_featured') ? 1 : 0,
            'status'         => $request->bool('active') ? 'active' : 'inactive',
            'sort_order'     => $request->int('sort_order', 0),
        ];

        return $this->persist(
            $id,
            $data,
            $name,
            'service',
            fn (array $d): int => $this->services->create($d),
            fn (int $i, array $d): bool => $this->services->update($i, $d),
            fn (int $i): ?array => $this->services->rawRow($i),
            fn (string $s, ?int $i): string => $this->services->uniqueSlug($s, $i),
            'services',
        );
    }

    public function deleteService(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->services->softDelete($id);
        $this->audit->record(AuditLogger::CONTENT_DELETED, 'service', $id, 'Removed service');

        return $this->redirectWithSuccess($this->config->adminPath . '/services', 'Service removed.');
    }

    // =================================================================
    //  Health packages
    // =================================================================

    public function packages(Request $request): Response
    {
        return $this->renderAdmin('admin/catalog/packages', [
            'packages' => $this->packages->adminList($request->input('status')),
            'meta'     => ['title' => 'Health Packages', 'noindex' => true],
        ]);
    }

    public function packageForm(Request $request): Response
    {
        $id      = $request->routeInt('id');
        $package = $id > 0 ? $this->packages->findById($id) : null;

        if ($id > 0 && $package === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/catalog/package-form', [
            'package' => $package,
            'meta'    => [
                'title'   => $package === null ? 'Add Package' : 'Edit ' . $package->title,
                'noindex' => true,
            ],
        ]);
    }

    public function savePackage(Request $request): Response
    {
        $id    = $request->routeInt('id');
        $title = $request->string('title');

        if ($title === '') {
            return $this->redirectWithError(
                $this->config->adminPath . '/packages',
                'The package title is required.',
            );
        }

        // The bullet lists arrive as newline-separated textareas, which is far
        // easier for staff than a repeater widget.
        $itemsEn = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $request->string('items_en')) ?: []));
        $itemsAm = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $request->string('items_am')) ?: []));
        $itemsOm = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $request->string('items_om')) ?: []));

        $data = [
            'title'          => $title,
            'title_am'       => $request->input('title_am'),
            'title_om'       => $request->input('title_om'),
            'price_etb'      => number_format(max(0, $request->float('price_etb')), 2, '.', ''),
            // Percentage in the form, fraction in the column.
            'deposit_rate'   => number_format(
                max(0, min(100, $request->float('deposit_percent', 30))) / 100,
                3,
                '.',
                '',
            ),
            'description'    => $request->input('description'),
            'description_am' => $request->input('description_am'),
            'description_om' => $request->input('description_om'),
            'items_json'     => PackageRepository::encodeItems(
                array_values($itemsEn),
                array_values($itemsAm),
                array_values($itemsOm),
            ),
            'badge'          => $request->input('badge'),
            'is_featured'    => $request->bool('is_featured') ? 1 : 0,
            'status'         => $request->bool('active') ? 'active' : 'inactive',
            'sort_order'     => $request->int('sort_order', 0),
        ];

        return $this->persist(
            $id,
            $data,
            $title,
            'package',
            fn (array $d): int => $this->packages->create($d),
            fn (int $i, array $d): bool => $this->packages->update($i, $d),
            fn (int $i): ?array => $this->packages->rawRow($i),
            fn (string $s, ?int $i): string => $this->packages->uniqueSlug($s, $i),
            'packages',
        );
    }

    public function deletePackage(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->packages->softDelete($id);
        $this->audit->record(AuditLogger::CONTENT_DELETED, 'package', $id, 'Removed health package');

        return $this->redirectWithSuccess($this->config->adminPath . '/packages', 'Package removed.');
    }

    // =================================================================
    //  Facilities
    // =================================================================

    public function facilities(Request $request): Response
    {
        return $this->renderAdmin('admin/catalog/facilities', [
            'facilities' => $this->facilities->adminList($request->input('status')),
            'statuses'   => FacilityStatus::all(),
            'counts'     => $this->facilities->statusCounts(),
            'meta'       => ['title' => 'Facilities', 'noindex' => true],
        ]);
    }

    public function facilityForm(Request $request): Response
    {
        $id       = $request->routeInt('id');
        $facility = $id > 0 ? $this->facilities->findById($id) : null;

        if ($id > 0 && $facility === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/catalog/facility-form', [
            'facility' => $facility,
            'statuses' => FacilityStatus::all(),
            'meta'     => [
                'title'   => $facility === null ? 'Add Facility' : 'Edit ' . $facility->name,
                'noindex' => true,
            ],
        ]);
    }

    public function saveFacility(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $name = $request->string('fac_name');

        if ($name === '') {
            return $this->redirectWithError(
                $this->config->adminPath . '/facilities',
                'The facility name is required.',
            );
        }

        $data = [
            'fac_name'       => $name,
            'name_am'        => $request->input('name_am'),
            'name_om'        => $request->input('name_om'),
            'type'           => $request->string('type', 'Clinical Room'),
            'room_label'     => $request->input('room_label'),
            'description'    => $request->input('description'),
            'description_am' => $request->input('description_am'),
            'description_om' => $request->input('description_om'),
            'status'         => (FacilityStatus::tryFrom($request->string('status')) ?? FacilityStatus::OPERATIONAL)->value,
            'notes'          => $request->input('notes'),
            'is_public'      => $request->bool('is_public') ? 1 : 0,
            'sort_order'     => $request->int('sort_order', 0),
        ];

        $image = $request->file('image');

        if ($image !== null) {
            $stored              = $this->uploader->storeMedia($image, 'facilities', 1400);
            $data['image_path']  = $stored->relativePath;
        }

        if ($id > 0) {
            $before = $this->facilities->rawRow($id);

            if ($before === null) {
                throw HttpException::notFound();
            }

            $this->facilities->update($id, $data);
            $this->audit->recordDiff(AuditLogger::CONTENT_UPDATED, 'facility', $id, $before, $data, 'Updated facility ' . $name);

            return $this->redirectWithSuccess($this->config->adminPath . '/facilities', 'Facility updated.');
        }

        $newId = $this->facilities->create($data);
        $this->audit->record(AuditLogger::CONTENT_CREATED, 'facility', $newId, 'Added facility ' . $name);

        return $this->redirectWithSuccess($this->config->adminPath . '/facilities', 'Facility added.');
    }

    public function deleteFacility(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->facilities->softDelete($id);
        $this->audit->record(AuditLogger::CONTENT_DELETED, 'facility', $id, 'Removed facility');

        return $this->redirectWithSuccess($this->config->adminPath . '/facilities', 'Facility removed.');
    }

    // =================================================================
    //  Shared create/update path
    // =================================================================

    /**
     * Insert or update a slugged catalogue row, with audit logging.
     *
     * The repositories have different concrete types but an identical call
     * shape, so the operations are passed in as closures rather than
     * duplicating this block three times.
     *
     * @param array<string, mixed> $data
     * @param callable(array<string, mixed>): int        $create
     * @param callable(int, array<string, mixed>): bool  $update
     * @param callable(int): (array<string, mixed>|null) $readRaw
     * @param callable(string, ?int): string             $slugger
     */
    private function persist(
        int $id,
        array $data,
        string $displayName,
        string $auditType,
        callable $create,
        callable $update,
        callable $readRaw,
        callable $slugger,
        string $listPath,
    ): Response {
        if ($id > 0) {
            $before = $readRaw($id);

            if ($before === null) {
                throw HttpException::notFound();
            }

            $titleColumn = isset($data['title']) ? 'title' : 'name';

            // Only re-slug when the public-facing name changed, so existing
            // URLs survive routine edits.
            if ((string) ($before[$titleColumn] ?? '') !== $displayName) {
                $data['slug'] = $slugger($displayName, $id);
            }

            $update($id, $data);

            $this->audit->recordDiff(
                AuditLogger::CONTENT_UPDATED,
                $auditType,
                $id,
                $before,
                $data,
                'Updated ' . $auditType . ' ' . $displayName,
            );

            return $this->redirectWithSuccess(
                $this->config->adminPath . '/' . $listPath,
                ucfirst($auditType) . ' updated.',
            );
        }

        $data['slug'] = $slugger($displayName, null);

        $newId = $create($data);

        $this->audit->record(
            AuditLogger::CONTENT_CREATED,
            $auditType,
            $newId,
            'Added ' . $auditType . ' ' . $displayName,
        );

        return $this->redirectWithSuccess(
            $this->config->adminPath . '/' . $listPath,
            ucfirst($auditType) . ' added.',
        );
    }
}
