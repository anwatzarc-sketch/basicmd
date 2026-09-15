<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\User;
use Aster\Domain\Repository\AuditLoggerInterface;
use Aster\Infrastructure\Support\Logger;
use Throwable;

/**
 * Append-only audit trail.
 *
 * Every state change a staff member makes lands here: who, what, when, from
 * which address, and the before/after values. This is the record that answers
 * "who cancelled that appointment" and "who approved this payment" - which is
 * a regulatory expectation for patient data, not a nice-to-have.
 *
 * Two deliberate choices:
 *
 *  - `actor_label` duplicates the user's name onto the row. The FK is
 *    ON DELETE SET NULL, so without the copy a deleted account would erase
 *    the identity from its own history.
 *  - A failure to write an audit row never breaks the request it describes.
 *    Losing the trail is bad; refusing to confirm a patient's appointment
 *    because the trail is unavailable is worse. Failures go to the error log.
 */
final class AuditLogger implements AuditLoggerInterface
{
    // Action constants, so a typo is a fatal rather than an unsearchable row.
    public const string LOGIN            = 'auth.login';
    public const string LOGIN_FAILED     = 'auth.login_failed';
    public const string LOGOUT           = 'auth.logout';
    public const string PASSWORD_CHANGED = 'auth.password_changed';
    public const string LOCKED_OUT       = 'auth.locked_out';

    public const string APPOINTMENT_CREATED   = 'appointment.created';
    public const string APPOINTMENT_STATUS    = 'appointment.status_changed';
    public const string APPOINTMENT_UPDATED   = 'appointment.updated';
    public const string APPOINTMENT_DELETED   = 'appointment.deleted';

    public const string PAYMENT_SUBMITTED = 'payment.proof_submitted';
    public const string PAYMENT_VERIFIED  = 'payment.verified';
    public const string PAYMENT_REJECTED  = 'payment.rejected';
    public const string PAYMENT_PROOF_VIEWED = 'payment.proof_viewed';

    public const string CONTENT_CREATED = 'content.created';
    public const string CONTENT_UPDATED = 'content.updated';
    public const string CONTENT_DELETED = 'content.deleted';

    public const string USER_CREATED  = 'user.created';
    public const string USER_UPDATED  = 'user.updated';
    public const string SETTINGS_SAVED = 'settings.saved';

    private ?int $userId = null;

    private ?string $actorLabel = null;

    private ?string $ipBinary = null;

    private string $userAgent = '';

    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    /** Bind the acting user and request context for the rest of the request. */
    public function withActor(?User $user, ?string $ipBinary, string $userAgent): self
    {
        $this->userId     = $user?->id;
        $this->actorLabel = $user !== null ? $user->fullName . ' (' . $user->role->value . ')' : null;
        $this->ipBinary   = $ipBinary;
        $this->userAgent  = mb_substr($userAgent, 0, 255);

        return $this;
    }

    /** Label an unauthenticated actor, e.g. a patient booking from the site. */
    public function asAnonymous(string $label, ?string $ipBinary, string $userAgent = ''): self
    {
        $this->userId     = null;
        $this->actorLabel = $label;
        $this->ipBinary   = $ipBinary;
        $this->userAgent  = mb_substr($userAgent, 0, 255);

        return $this;
    }

    /**
     * @param array{before?: array<string,mixed>, after?: array<string,mixed>}|null $changes
     */
    public function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $summary = null,
        ?array $changes = null,
    ): void {
        try {
            $this->db->execute(
                'INSERT INTO audit_logs
                    (user_id, actor_label, action, target_type, target_id, summary, changes_json, ip_address, user_agent)
                 VALUES (:uid, :actor, :action, :ttype, :tid, :summary, :changes, :ip, :ua)',
                [
                    'uid'     => $this->userId,
                    'actor'   => $this->actorLabel,
                    'action'  => $action,
                    'ttype'   => $targetType,
                    'tid'     => $targetId,
                    'summary' => $summary !== null ? mb_substr($summary, 0, 500) : null,
                    'changes' => $changes !== null ? $this->encodeChanges($changes) : null,
                    'ip'      => $this->ipBinary,
                    'ua'      => $this->userAgent,
                ],
            );
        } catch (Throwable $e) {
            $this->logger->error('Audit write failed', [
                'action' => $action,
                'target' => $targetType . ':' . $targetId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record only the fields that actually changed.
     *
     * Storing a full row twice on every save bloats the table and buries the
     * one field that moved. Nothing is written when nothing differs.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function recordDiff(
        string $action,
        string $targetType,
        int $targetId,
        array $before,
        array $after,
        ?string $summary = null,
    ): void {
        $changedBefore = [];
        $changedAfter  = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            // Loose-ish comparison on scalars: PDO may hand back "1" where
            // the form submitted 1, and that is not a real change.
            if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
                continue;
            }

            $changedBefore[$key] = $oldValue;
            $changedAfter[$key]  = $newValue;
        }

        if ($changedAfter === []) {
            return;
        }

        $this->record($action, $targetType, $targetId, $summary, [
            'before' => $changedBefore,
            'after'  => $changedAfter,
        ]);
    }

    /**
     * Redact sensitive fields before they reach the audit table.
     *
     * The trail records that a password or a patient's clinical note changed,
     * never what it changed to.
     */
    private function encodeChanges(array $changes): string
    {
        $sensitive = ['password', 'password_hash', 'token', 'token_hash', 'patient_notes', 'notes'];

        array_walk_recursive($changes, static function (mixed &$value, string|int $key) use ($sensitive): void {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $value = '[redacted]';
            }
        });

        $encoded = json_encode(
            $changes,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Paginated trail for the audit screen.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50, int $offset = 0, ?string $action = null, ?int $userId = null): array
    {
        $where  = [];
        $params = [];

        if ($action !== null && $action !== '') {
            $where[]          = 'a.action = :action';
            $params['action'] = $action;
        }

        if ($userId !== null) {
            $where[]        = 'a.user_id = :uid';
            $params['uid']  = $userId;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        return $this->db->fetchAll(
            'SELECT a.*, INET6_NTOA(a.ip_address) AS ip_text, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id'
            . $clause .
            ' ORDER BY a.id DESC
             LIMIT :limit OFFSET :offset',
            $params,
        );
    }

    public function countAll(?string $action = null, ?int $userId = null): int
    {
        $where  = [];
        $params = [];

        if ($action !== null && $action !== '') {
            $where[]          = 'action = :action';
            $params['action'] = $action;
        }

        if ($userId !== null) {
            $where[]       = 'user_id = :uid';
            $params['uid'] = $userId;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return $this->db->fetchInt('SELECT COUNT(*) FROM audit_logs' . $clause, $params);
    }

    /** History for one record, shown on its detail page. */
    public function forTarget(string $targetType, int $targetId, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, INET6_NTOA(a.ip_address) AS ip_text, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.target_type = :t AND a.target_id = :id
             ORDER BY a.id DESC
             LIMIT :limit',
            ['t' => $targetType, 'id' => $targetId, 'limit' => $limit],
        );
    }

    /** Retention pruning for the cron job. */
    public function pruneOlderThan(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :d DAY)',
            ['d' => $days],
        );
    }
}
