<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Exception\HttpException;
use Aster\Domain\Support\PermissionCatalog;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\RoleRepository;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Persistence\WardLocationRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Role Management (spec §4.2) - list/create/edit/archive roles, and
 * assign a role to a user with an optional ward scope (§4.5).
 *
 * roles.manage-gated, super_admin-only, no delegation path - enforced at
 * the route table (can:roles.manage), not here; this controller assumes
 * it and never re-checks a role name.
 *
 * No starter-template prefill on create - there is no pre-existing role
 * to prefill from (spec §4.2), so the checklist always starts from the
 * full catalogue with nothing pre-ticked.
 */
final class RoleController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly RoleRepository $roles,
        private readonly UserRepository $users,
        private readonly WardLocationRepository $wardLocations,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        return $this->renderAdmin('admin/roles/index', [
            'roles' => $this->roles->all(),
            'meta'  => ['title' => 'Roles', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $role = $id > 0 ? $this->roles->find($id) : null;

        if ($id > 0 && $role === null) {
            throw HttpException::notFound();
        }

        $permissions = $this->roles->allPermissions();
        $slugs       = array_column($permissions, 'slug');

        return $this->renderAdmin('admin/roles/form', [
            'role'        => $role,
            'permissions' => $permissions,
            'groups'      => PermissionCatalog::grouped($slugs),
            'meta'        => [
                'title'   => $role === null ? 'Create Role' : 'Edit ' . $role->label,
                'noindex' => true,
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $id           = $request->routeInt('id');
        $label        = trim($request->string('label'));
        $permissionIds = array_map('intval', $request->array('permissions'));

        $formPath = $this->config->adminPath . '/roles' . ($id > 0 ? '/' . $id . '/edit' : '/create');

        if ($label === '') {
            return $this->redirectWithError($formPath, 'Please name the role.');
        }

        if ($id > 0) {
            $role = $this->roles->find($id);

            if ($role === null) {
                throw HttpException::notFound();
            }

            $this->roles->rename($id, $label);
            $this->roles->updatePermissions($id, $permissionIds);

            $this->audit->record(
                AuditLogger::USER_UPDATED,
                'role',
                $id,
                sprintf('Updated role %s - %d permission(s)', $label, count($permissionIds)),
            );

            return $this->redirectWithSuccess($this->config->adminPath . '/roles', 'Role updated.');
        }

        $slug = $this->slugify($label);

        if ($slug === '' || $this->roles->slugExists($slug)) {
            return $this->redirectWithError($formPath, 'That role name is already in use or produces an empty slug.');
        }

        $roleId = $this->roles->create($slug, $label, $permissionIds);

        $this->audit->record(
            AuditLogger::USER_CREATED,
            'role',
            $roleId,
            sprintf('Created role %s - %d permission(s)', $label, count($permissionIds)),
        );

        return $this->redirectWithSuccess($this->config->adminPath . '/roles', 'Role created.');
    }

    /** Archive rather than hard-delete (spec §4.2) - refuses while assigned. */
    public function archive(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $role = $this->roles->find($id);

        if ($role === null) {
            throw HttpException::notFound();
        }

        if (!$this->roles->archive($id)) {
            return $this->redirectWithError(
                $this->config->adminPath . '/roles',
                sprintf('%s is still assigned to %d user(s) - reassign them first.', $role->label, $role->userCount),
            );
        }

        $this->audit->record(AuditLogger::USER_UPDATED, 'role', $id, 'Archived role ' . $role->label);

        return $this->redirectWithSuccess($this->config->adminPath . '/roles', 'Role archived.');
    }

    public function unarchive(Request $request): Response
    {
        $id = $request->routeInt('id');

        if (!$this->roles->unarchive($id)) {
            throw HttpException::notFound();
        }

        $this->audit->record(AuditLogger::USER_UPDATED, 'role', $id, 'Unarchived role');

        return $this->redirectWithSuccess($this->config->adminPath . '/roles', 'Role restored.');
    }

    /** Assign-to-user step, with an optional ward-scope multiselect (spec §4.5). */
    public function assignForm(Request $request): Response
    {
        $wardNames = array_values(array_unique(array_map(
            static fn ($ward): string => $ward->wardName,
            $this->wardLocations->all(),
        )));

        return $this->renderAdmin('admin/roles/assign', [
            'users'     => $this->users->all(),
            'roles'     => $this->roles->all(false),
            'wardNames' => $wardNames,
            'meta'      => ['title' => 'Assign Role', 'noindex' => true],
        ]);
    }

    public function assign(Request $request): Response
    {
        $userId   = $request->int('user_id');
        $roleId   = $request->int('role_id');
        $wardNames = array_values(array_filter(array_map('trim', $request->array('ward_names'))));

        $back = $this->config->adminPath . '/roles/assign';

        $user = $this->users->findById($userId);
        $role = $this->roles->find($roleId);

        if ($user === null || $role === null) {
            return $this->redirectWithError($back, 'Choose a valid user and role.');
        }

        if ($role->isArchived()) {
            return $this->redirectWithError($back, 'That role is archived and cannot accept new assignments.');
        }

        $this->roles->assignToUser($userId, $roleId, $wardNames);

        $this->audit->record(
            AuditLogger::USER_UPDATED,
            'user',
            $userId,
            sprintf(
                'Assigned role %s to %s%s',
                $role->label,
                $user->fullName,
                $wardNames === [] ? ' (unscoped)' : ' (scoped to: ' . implode(', ', $wardNames) . ')',
            ),
        );

        return $this->redirectWithSuccess($back, sprintf('%s assigned to %s.', $role->label, $user->fullName));
    }

    public function revoke(Request $request): Response
    {
        $userId = $request->int('user_id');
        $roleId = $request->int('role_id');

        $user = $this->users->findById($userId);
        $role = $this->roles->find($roleId);

        if ($user === null || $role === null) {
            throw HttpException::notFound();
        }

        $this->roles->revokeFromUser($userId, $roleId);

        $this->audit->record(
            AuditLogger::USER_UPDATED,
            'user',
            $userId,
            sprintf('Revoked role %s from %s', $role->label, $user->fullName),
        );

        return $this->redirectWithSuccess($this->config->adminPath . '/roles/assign', 'Role revoked.');
    }

    /**
     * A URL-safe slug from an admin-typed label - the underlying storage
     * key, never shown, so it just needs to be stable and unique, not
     * pretty.
     */
    private function slugify(string $label): string
    {
        $slug = mb_strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';

        return trim($slug, '_');
    }
}
