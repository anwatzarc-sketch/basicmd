<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller;

use Aster\Domain\Entity\Patient;
use Aster\Domain\Entity\User;
use Aster\Domain\Enum\Locale;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Shared controller behaviour.
 *
 * Deliberately thin: view rendering, redirects with flash messages, and the
 * validation-failure round trip. Business logic belongs in the application
 * services, which is what keeps controllers readable and testable.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly SessionManager $session,
        protected readonly Config $config,
    ) {
    }

    /**
     * Render a template inside a layout.
     *
     * Flash messages, old input and validation errors are injected into every
     * page automatically, so no controller has to remember to pass them.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'layouts/public'): Response
    {
        $this->view->share([
            'view'      => $this->view,
            'flash'     => $this->session->flashMessages(),
            'old'       => $this->session->oldInput(),
            'errors'    => $this->session->errors(),
            'locale'    => $this->currentLocale(),
            'user'      => $this->currentUser(),
            'cspNonce'  => $GLOBALS['aster_csp_nonce'] ?? '',
        ]);

        return Response::html($this->view->renderWithLayout($template, $layout, $data));
    }

    /** @param array<string, mixed> $data */
    protected function renderAdmin(string $template, array $data = []): Response
    {
        return $this->render($template, $data, 'layouts/admin');
    }

    /** @param array<string, mixed> $data */
    protected function renderPortal(string $template, array $data = []): Response
    {
        return $this->render($template, $data, 'layouts/portal');
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(
            str_starts_with($path, 'http') ? $path : $this->config->url($path)
        );
    }

    protected function redirectToAdmin(string $path = ''): Response
    {
        return Response::redirect($this->config->adminUrl($path));
    }

    /** Redirect carrying a success banner. */
    protected function redirectWithSuccess(string $path, string $message): Response
    {
        $this->session->flash('success', $message);

        return $this->redirect($path);
    }

    protected function redirectWithError(string $path, string $message): Response
    {
        $this->session->flash('error', $message);

        return $this->redirect($path);
    }

    /**
     * Send the user back to a form with their input and the field errors.
     *
     * Re-populating the form matters: a patient who mistypes a phone number
     * on a nine-field booking form will abandon it rather than retype
     * everything, and that is a lost appointment.
     */
    protected function redirectWithValidation(
        string $path,
        ValidationException $exception,
        Request $request,
    ): Response {
        $this->session->flashErrors($exception->flatErrors());
        $this->session->flashInput($request->body);
        $this->session->flash('error', $exception->getMessage());

        return $this->redirect($path);
    }

    /**
     * Respond to a validation failure in the format the client asked for.
     */
    protected function validationResponse(
        ValidationException $exception,
        Request $request,
        string $redirectPath,
    ): Response {
        if ($request->wantsJson()) {
            return Response::json([
                'ok'      => false,
                'message' => $exception->getMessage(),
                'errors'  => $exception->flatErrors(),
            ], 422);
        }

        return $this->redirectWithValidation($redirectPath, $exception, $request);
    }

    protected function currentUser(): ?User
    {
        $user = $GLOBALS['aster_current_user'] ?? null;

        return $user instanceof User ? $user : null;
    }

    /** The authenticated user, for routes behind the Authenticate middleware. */
    protected function requireUser(): User
    {
        $user = $this->currentUser();

        if ($user === null) {
            // Unreachable behind Authenticate; failing loudly beats a
            // null-dereference further down.
            throw \Aster\Domain\Exception\HttpException::unauthorized();
        }

        return $user;
    }

    protected function currentPatient(): ?Patient
    {
        $patient = $GLOBALS['aster_current_patient'] ?? null;

        return $patient instanceof Patient ? $patient : null;
    }

    /** The authenticated patient, for routes behind the AuthenticatePatient middleware. */
    protected function requirePatient(): Patient
    {
        $patient = $this->currentPatient();

        if ($patient === null) {
            // Unreachable behind AuthenticatePatient; failing loudly beats a
            // null-dereference further down.
            throw \Aster\Domain\Exception\HttpException::unauthorized();
        }

        return $patient;
    }

    protected function currentLocale(): Locale
    {
        $locale = $GLOBALS['aster_locale'] ?? null;

        return $locale instanceof Locale ? $locale : $this->config->defaultLocale;
    }

    /**
     * Clamp a page number and produce a LIMIT/OFFSET pair.
     *
     * @return array{page:int, perPage:int, offset:int}
     */
    protected function paginate(Request $request, int $perPage = 25, int $maxPerPage = 100): array
    {
        $page    = max(1, $request->int('page', 1));
        $perPage = min($maxPerPage, max(5, $request->int('per_page', $perPage)));

        return [
            'page'    => $page,
            'perPage' => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ];
    }

    /**
     * Pagination metadata for the template.
     *
     * @return array<string, int|bool>
     */
    protected function paginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'total'    => $total,
            'page'     => min($page, $lastPage),
            'perPage'  => $perPage,
            'lastPage' => $lastPage,
            'from'     => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to'       => min($total, $page * $perPage),
            'hasPrev'  => $page > 1,
            'hasNext'  => $page < $lastPage,
        ];
    }
}
