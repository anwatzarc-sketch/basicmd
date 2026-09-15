<?php

declare(strict_types=1);

namespace Aster\Presentation\Controller\Admin;

use Aster\Infrastructure\Mail\Mailer;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Controller;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;

/**
 * Clinic settings, the audit trail, and mail-queue health.
 *
 * Only keys that already exist in system_settings can be written, so adding
 * fields to the POST body cannot inject new configuration.
 */
final class SettingsController extends Controller
{
    public function __construct(
        View $view,
        SessionManager $session,
        Config $config,
        private readonly SettingsRepository $settings,
        private readonly MailQueue $queue,
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
    ) {
        parent::__construct($view, $session, $config);
    }

    public function index(Request $request): Response
    {
        $groups = [];

        foreach ($this->settings->groups() as $group) {
            $groups[$group] = $this->settings->group($group);
        }

        return $this->renderAdmin('admin/settings/index', [
            'groups'     => $groups,
            'mailStats'  => $this->queue->stats(),
            'meta'       => ['title' => 'Clinic Settings', 'noindex' => true],
        ]);
    }

    public function save(Request $request): Response
    {
        $user = $this->requireUser();

        // The form posts settings[key] => value. Unknown keys are ignored by
        // saveMany(), which only UPDATEs existing rows.
        $submitted = $request->body['settings'] ?? [];

        if (!is_array($submitted)) {
            return $this->redirectWithError($this->config->adminPath . '/settings', 'Nothing to save.');
        }

        $values  = [];
        $before  = [];

        foreach ($submitted as $key => $value) {
            if (!is_string($key) || is_array($value)) {
                continue;
            }

            $before[$key] = $this->settings->get($key);
            $values[$key] = trim((string) $value);
        }

        // Unchecked checkboxes are absent from the POST entirely, so every
        // boolean key is reconciled explicitly.
        foreach ($request->array('bool_keys') as $boolKey) {
            $before[$boolKey] = $this->settings->get($boolKey);
            $values[$boolKey] = isset($submitted[$boolKey]) ? '1' : '0';
        }

        $updated = $this->settings->saveMany($values, $user->id);

        $this->audit->recordDiff(
            AuditLogger::SETTINGS_SAVED,
            'settings',
            0,
            $before,
            $values,
            sprintf('Updated %d clinic settings', $updated),
        );

        return $this->redirectWithSuccess($this->config->adminPath . '/settings', 'Settings saved.');
    }

    /** The audit trail viewer. */
    public function audit(Request $request): Response
    {
        ['page' => $page, 'perPage' => $perPage, 'offset' => $offset] = $this->paginate($request, 50);

        $action = $request->input('action');
        $userId = $request->nullableInt('user_id');

        $total = $this->audit->countAll($action, $userId);

        return $this->renderAdmin('admin/settings/audit', [
            'entries'    => $this->audit->recent($perPage, $offset, $action, $userId),
            'filters'    => ['action' => $action, 'user_id' => $userId],
            'pagination' => $this->paginationMeta($total, $page, $perPage),
            'meta'       => ['title' => 'Audit Trail', 'noindex' => true],
        ]);
    }

    /** Mail queue status, with retry for failed messages. */
    public function mail(Request $request): Response
    {
        return $this->renderAdmin('admin/settings/mail', [
            'stats'    => $this->queue->stats(),
            'failures' => $this->queue->failures(50),
            'driver'   => $this->mailer->isLogDriver() ? 'log' : 'smtp',
            'meta'     => ['title' => 'Email Queue', 'noindex' => true],
        ]);
    }

    public function retryMail(Request $request): Response
    {
        $id = $request->routeInt('id');

        $retried = $this->queue->retry($id);

        return $retried
            ? $this->redirectWithSuccess($this->config->adminPath . '/settings/mail', 'Message queued for retry.')
            : $this->redirectWithError($this->config->adminPath . '/settings/mail', 'That message could not be retried.');
    }

    /** Check SMTP connectivity without sending a message. */
    public function testMail(Request $request): Response
    {
        $result = $this->mailer->testConnection();

        return $result['ok']
            ? $this->redirectWithSuccess($this->config->adminPath . '/settings/mail', $result['message'])
            : $this->redirectWithError($this->config->adminPath . '/settings/mail', 'Mail check failed: ' . $result['message']);
    }
}
