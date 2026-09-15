<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Application\Service\AuthService;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Staff sign-in, sign-out and password management.
 */
final class AuthController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly AuthService $auth,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function loginForm(Request $request): Response
    {
        // An already-authenticated user landing on /login goes to their
        // role's home instead of being shown a pointless form.
        $user = $this->auth->currentUser();

        if ($user !== null) {
            return $this->redirectToAdmin(ltrim($user->role->homeRoute(), '/'));
        }

        return $this->render('admin/login', [
            'return' => $request->safeRedirectTarget('return', ''),
            'meta'   => ['title' => 'Staff Sign In', 'noindex' => true],
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        try {
            $user = $this->auth->login(
                email:     $request->string('email'),
                password:  $request->string('password'),
                ip:        $request->ip($this->config->trustProxy()),
                ipBinary:  $request->ipBinary($this->config->trustProxy()),
                userAgent: $request->userAgent(),
            );
        } catch (ValidationException $e) {
            $this->session->flashErrors($e->flatErrors());
            // The email is preserved so a mistyped password does not cost the
            // user both fields; the password itself is stripped by flashInput.
            $this->session->flashInput(['email' => $request->string('email')]);
            $this->session->flash('error', $e->getMessage());

            return $this->redirectToAdmin('login');
        } catch (HttpException $e) {
            $this->session->flash('error', $e->getMessage());

            return $this->redirectToAdmin('login');
        }

        $this->session->flash('success', 'Welcome back, ' . $user->shortName() . '.');

        if ($user->mustChangePassword) {
            return $this->redirectToAdmin('password');
        }

        // safeRedirectTarget rejects absolute and protocol-relative URLs, so
        // ?return= cannot be turned into an open redirect.
        $return = $request->safeRedirectTarget('return', '');

        return $return !== '' && $return !== '/'
            ? Response::redirect($return)
            : $this->redirectToAdmin(ltrim($user->role->homeRoute(), '/'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout(
            $this->currentUser(),
            $request->ipBinary($this->config->trustProxy()),
            $request->userAgent(),
        );

        // A fresh session is started so the flash message survives the
        // destroy() above.
        $this->session->start();
        $this->session->flash('success', 'You have been signed out.');

        return $this->redirectToAdmin('login');
    }

    public function passwordForm(Request $request): Response
    {
        $user = $this->requireUser();

        return $this->renderAdmin('admin/password', [
            'forced' => $user->mustChangePassword,
            'meta'   => ['title' => 'Change Password', 'noindex' => true],
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->requireUser();

        try {
            $this->auth->changePassword(
                $user,
                $request->string('current_password'),
                $request->string('password'),
                $request->string('password_confirmation'),
            );
        } catch (ValidationException $e) {
            $this->session->flashErrors($e->flatErrors());
            $this->session->flash('error', $e->getMessage());

            return $this->redirectToAdmin('password');
        }

        $this->session->flash('success', 'Your password has been updated.');

        return $this->redirectToAdmin(ltrim($user->role->homeRoute(), '/'));
    }
}
