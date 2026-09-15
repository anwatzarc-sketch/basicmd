<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Domain\Enum\UserRole;
use Aster\Domain\Enum\UserStatus;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\PasswordHasher;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Staff account administration (SuperAdmin only).
 *
 * Two safeguards prevent an administrator locking the organisation out of its
 * own system: you cannot suspend or delete your own account, and the last
 * active SuperAdmin cannot be demoted or removed.
 */
final class UserController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly UserRepository $users,
        private readonly DoctorRepository $doctors,
        private readonly PasswordHasher $hasher,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        return $this->renderAdmin('admin/users/index', [
            'users'    => $this->users->all($request->input('role'), $request->input('status')),
            'roles'    => UserRole::all(),
            'statuses' => UserStatus::all(),
            'counts'   => $this->users->countByRole(),
            'filters'  => ['role' => $request->input('role'), 'status' => $request->input('status')],
            'meta'     => ['title' => 'Staff Accounts', 'noindex' => true],
        ]);
    }

    public function form(Request $request): Response
    {
        $id   = $request->routeInt('id');
        $user = $id > 0 ? $this->users->findById($id) : null;

        if ($id > 0 && $user === null) {
            throw HttpException::notFound();
        }

        return $this->renderAdmin('admin/users/form', [
            'account'  => $user,
            'roles'    => UserRole::all(),
            'statuses' => UserStatus::all(),
            'doctors'  => $this->doctors->options(false),
            'meta'     => [
                'title'   => $user === null ? 'Add Staff Account' : 'Edit ' . $user->fullName,
                'noindex' => true,
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $actor = $this->requireUser();
        $id    = $request->routeInt('id');

        $name  = $request->string('full_name');
        $email = mb_strtolower($request->string('email'));
        $role  = UserRole::tryFrom($request->string('role')) ?? UserRole::RECEPTIONIST;

        $formPath = $this->config->adminPath . '/users' . ($id > 0 ? '/' . $id . '/edit' : '/create');

        $errors = [];

        if ($name === '') {
            $errors['full_name'][] = 'Please enter a name.';
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Please enter a valid email address.';
        } elseif ($this->users->emailExists($email, $id > 0 ? $id : null)) {
            $errors['email'][] = 'That email address is already registered.';
        }

        if ($errors !== []) {
            return $this->redirectWithValidation($formPath, ValidationException::withErrors($errors), $request);
        }

        $status = UserStatus::tryFrom($request->string('status')) ?? UserStatus::ACTIVE;

        if ($id > 0) {
            $existing = $this->users->findById($id);

            if ($existing === null) {
                throw HttpException::notFound();
            }

            // Self-lockout guard.
            if ($existing->id === $actor->id && ($status !== UserStatus::ACTIVE || $role !== $actor->role)) {
                return $this->redirectWithError(
                    $formPath,
                    'You cannot change your own role or suspend your own account.',
                );
            }

            // Last-admin guard: demoting or suspending the only remaining
            // SuperAdmin would leave nobody able to manage the system.
            if ($existing->role === UserRole::SUPER_ADMIN
                && ($role !== UserRole::SUPER_ADMIN || $status !== UserStatus::ACTIVE)
                && $this->countActiveSuperAdmins() <= 1
            ) {
                return $this->redirectWithError(
                    $formPath,
                    'This is the last active administrator. Promote another account first.',
                );
            }

            $before = $this->users->rawRow($id);

            $this->users->update($id, [
                'full_name' => $name,
                'email'     => $email,
                'phone'     => $request->input('phone'),
                'role'      => $role->value,
                'status'    => $status->value,
                'locale'    => $request->string('locale', 'en') === 'am' ? 'am' : 'en',
            ]);

            $this->linkDoctorProfile($id, $request->nullableInt('doctor_id'));

            $this->audit->recordDiff(
                AuditLogger::USER_UPDATED,
                'user',
                $id,
                $before ?? [],
                ['full_name' => $name, 'email' => $email, 'role' => $role->value, 'status' => $status->value],
                'Updated staff account ' . $name,
            );

            return $this->redirectWithSuccess($this->config->adminPath . '/users', 'Account updated.');
        }

        // New accounts get a temporary password that must be changed on
        // first use, so no permanent credential is ever chosen by someone
        // other than its owner.
        $temporary = PasswordHasher::generateTemporary();

        $newId = $this->users->create(
            fullName:           $name,
            email:              $email,
            passwordHash:       $this->hasher->hash($temporary),
            role:               $role,
            phone:              $request->input('phone'),
            status:             $status->value,
            mustChangePassword: true,
        );

        $this->linkDoctorProfile($newId, $request->nullableInt('doctor_id'));

        $this->audit->record(
            AuditLogger::USER_CREATED,
            'user',
            $newId,
            sprintf('Created %s account for %s', $role->label(), $name),
        );

        // Shown once, on screen only. It is never emailed and never logged:
        // a temporary password in an inbox or a log file is a standing risk.
        $this->session->flash(
            'success',
            sprintf(
                'Account created for %s. Temporary password: %s - share it securely and it must be changed at first sign-in.',
                $name,
                $temporary,
            ),
        );

        return $this->redirectToAdmin('users');
    }

    public function resetPassword(Request $request): Response
    {
        $actor  = $this->requireUser();
        $id     = $request->routeInt('id');
        $target = $this->users->findById($id);

        if ($target === null) {
            throw HttpException::notFound();
        }

        $temporary = PasswordHasher::generateTemporary();

        $this->users->updatePassword($id, $this->hasher->hash($temporary));
        $this->users->update($id, ['must_change_password' => 1]);

        $this->audit->record(
            AuditLogger::PASSWORD_CHANGED,
            'user',
            $id,
            sprintf('Password reset for %s by %s', $target->fullName, $actor->fullName),
        );

        $this->session->flash(
            'success',
            sprintf('Temporary password for %s: %s - they must change it at next sign-in.', $target->fullName, $temporary),
        );

        return $this->redirectToAdmin('users');
    }

    public function unlock(Request $request): Response
    {
        $id = $request->routeInt('id');

        $this->users->unlock($id);
        $this->audit->record(AuditLogger::USER_UPDATED, 'user', $id, 'Account lockout cleared');

        return $this->redirectWithSuccess($this->config->adminPath . '/users', 'Account unlocked.');
    }

    public function delete(Request $request): Response
    {
        $actor  = $this->requireUser();
        $id     = $request->routeInt('id');
        $target = $this->users->findById($id);

        if ($target === null) {
            throw HttpException::notFound();
        }

        if ($target->id === $actor->id) {
            return $this->redirectWithError($this->config->adminPath . '/users', 'You cannot delete your own account.');
        }

        if ($target->role === UserRole::SUPER_ADMIN && $this->countActiveSuperAdmins() <= 1) {
            return $this->redirectWithError(
                $this->config->adminPath . '/users',
                'This is the last active administrator and cannot be deleted.',
            );
        }

        $this->users->softDelete($id);

        $this->audit->record(AuditLogger::USER_UPDATED, 'user', $id, 'Deleted staff account ' . $target->fullName);

        return $this->redirectWithSuccess($this->config->adminPath . '/users', 'Account deleted.');
    }

    private function countActiveSuperAdmins(): int
    {
        return $this->users->countByRole()[UserRole::SUPER_ADMIN->value] ?? 0;
    }

    /**
     * Link or unlink a login to a doctor profile.
     *
     * The link is what scopes a clinician to their own appointment queue, so
     * a doctor account without one deliberately sees nothing.
     */
    private function linkDoctorProfile(int $userId, ?int $doctorId): void
    {
        // Clear any previous link first, since the column is unique.
        $existing = $this->doctors->findByUserId($userId);

        if ($existing !== null && $existing->id !== $doctorId) {
            $this->doctors->update($existing->id, ['user_id' => null]);
        }

        if ($doctorId !== null && $doctorId > 0) {
            $this->doctors->update($doctorId, ['user_id' => $userId]);
        }
    }
}
