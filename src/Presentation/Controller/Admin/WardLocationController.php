<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Exception\HttpException;
use Aster\Domain\ValueObject\Money;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\WardLocationRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Ward Locations admin screen (spec §4.5) - registers ward_name/
 * room_number/bed_number and is_transient. wards.manage-gated.
 *
 * Occupancy (is_occupied) is display-only here, per the spec and the
 * Ground Rules: it is concurrency-guarded elsewhere via
 * WardLocationRepositoryInterface::findForUpdate()'s row lock for real
 * bed allocation (EncounterService::upgradeOpdToIpd()), and this screen
 * never writes it - no markOccupied()/markAvailable() call appears below,
 * deliberately, so registering a bed here can never race that lock.
 */
final class WardLocationController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly WardLocationRepository $wards,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        return $this->renderAdmin('admin/wards/index', [
            'wards' => $this->wards->all(),
            'meta'  => ['title' => 'Ward Locations', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $ward = $id > 0 ? $this->wards->find($id) : null;

        if ($id > 0 && $ward === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/wards/form', [
            'ward' => $ward,
            'meta' => ['title' => $ward === null ? 'Register Bed' : 'Edit Bed', 'noindex' => true],
        ]);
    }

    public function save(Request $request): Response
    {
        $id          = $request->routeInt('id');
        $wardName    = trim($request->string('ward_name'));
        $roomNumber  = trim($request->string('room_number'));
        $bedNumber   = trim($request->string('bed_number'));
        $isTransient = $request->bool('is_transient');
        $dailyRate   = $request->float('daily_rate', 0.0);

        $formPath = $this->config->adminPath . '/wards' . ($id > 0 ? '/' . $id . '/edit' : '/create');

        if ($wardName === '' || $roomNumber === '' || $bedNumber === '') {
            return $this->redirectWithError($formPath, 'Ward name, room number and bed number are all required.');
        }

        $data = [
            'ward_name'    => $wardName,
            'room_number'  => $roomNumber,
            'bed_number'   => $bedNumber,
            'is_transient' => $isTransient ? 1 : 0,
            'daily_rate'   => Money::fromMajor($dailyRate)->toDatabase(),
        ];

        if ($id > 0) {
            if ($this->wards->find($id) === null) {
                throw HttpException::notFound();
            }

            $this->wards->update($id, $data);

            $this->audit->record(AuditLogger::USER_UPDATED, 'ward_location', $id, "Updated bed {$wardName}/{$roomNumber}/{$bedNumber}");

            return $this->redirectWithSuccess($this->config->adminPath . '/wards', 'Bed updated.');
        }

        try {
            $newId = $this->wards->create($data);
        } catch (\Throwable) {
            // uq_ward_locations_bed(ward_name, room_number, bed_number) -
            // the same bed registered twice.
            return $this->redirectWithError($formPath, 'That ward/room/bed combination is already registered.');
        }

        $this->audit->record(AuditLogger::USER_CREATED, 'ward_location', $newId, "Registered bed {$wardName}/{$roomNumber}/{$bedNumber}");

        return $this->redirectWithSuccess($this->config->adminPath . '/wards', 'Bed registered.');
    }
}
