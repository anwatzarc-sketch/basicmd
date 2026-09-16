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

    public const string PORTAL_ACCESS_PROVISIONED = 'portal.access_provisioned';
    public const string PORTAL_LOGIN               = 'portal.login';
    public const string PORTAL_LOGIN_FAILED        = 'portal.login_failed';
    public const string PORTAL_LOGOUT              = 'portal.logout';

    public const string CLINICAL_NOTE_CREATED      = 'clinical_note.created';
    public const string DIAGNOSTIC_ORDER_CREATED   = 'diagnostic_order.created';
    public const string DIAGNOSTIC_RESULT_RECORDED = 'diagnostic_order.result_recorded';
    public const string PRESCRIPTION_CREATED       = 'prescription.created';
    public const string PRESCRIPTION_DISPENSED     = 'prescription.dispensed';

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
        $this->actorLabel = $user !== null ? $user->fullName . ' (' . $user->roleLabel . ')' : null;
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
        $sensitive = [
            'password', 'password_hash', 'token', 'token_hash', 'patient_notes', 'notes',
            // Phase II: clinical text and structured results, in whatever
            // form they appear in a diff payload - ciphertext included,
            // since the field NAME being visible in an audit row is
            // already more than an audit trail needs to show, encrypted or
            // not. See Encryptor's docblock and Patient's
            // allergies_encrypted for the same boundary.
            'content_encrypted', 'results_payload_encrypted', 'allergies_encrypted',
            'vitals_json',
        ];

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
     * Build the shared WHERE clause for recent()/countAll() once, so the two
     * queries can never silently drift apart on what "matching filters"
     * means. $columnPrefix is 'a.' for the joined recent() query and '' for
     * countAll()'s unjoined one.
     *
     * @return array{clause: string, params: array<string, mixed>}
     */
    private function filterClause(
        string $columnPrefix,
        ?string $action,
        ?int $userId,
        ?string $targetType,
        ?string $search,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $where  = [];
        $params = [];

        if ($action !== null && $action !== '') {
            $where[]          = "{$columnPrefix}action = :action";
            $params['action'] = $action;
        }

        if ($userId !== null) {
            $where[]       = "{$columnPrefix}user_id = :uid";
            $params['uid'] = $userId;
        }

        if ($targetType !== null && $targetType !== '') {
            $where[]              = "{$columnPrefix}target_type = :ttype";
            $params['ttype']      = $targetType;
        }

        if ($search !== null && $search !== '') {
            // Three separate placeholders bound to the same value - PDO
            // with ATTR_EMULATE_PREPARES off (see bin/check-sql.php) allows
            // a named placeholder only once per statement, so :search
            // itself cannot be reused across the OR'd columns.
            $where[] = "({$columnPrefix}summary LIKE :search1"
                . " OR {$columnPrefix}actor_label LIKE :search2"
                . " OR {$columnPrefix}target_id = :search3)";
            $needle             = '%' . $search . '%';
            $params['search1']  = $needle;
            $params['search2']  = $needle;
            // target_id is numeric; a non-numeric search term simply never
            // matches this branch rather than throwing.
            $params['search3']  = ctype_digit($search) ? (int) $search : -1;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $where[]              = "{$columnPrefix}created_at >= :dfrom";
            $params['dfrom']      = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== null && $dateTo !== '') {
            $where[]            = "{$columnPrefix}created_at <= :dto";
            $params['dto']      = $dateTo . ' 23:59:59';
        }

        return [
            'clause' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }

    /**
     * Paginated trail for the audit screen.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(
        int $limit = 50,
        int $offset = 0,
        ?string $action = null,
        ?int $userId = null,
        ?string $targetType = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        ['clause' => $clause, 'params' => $params] = $this->filterClause(
            'a.', $action, $userId, $targetType, $search, $dateFrom, $dateTo,
        );

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

    public function countAll(
        ?string $action = null,
        ?int $userId = null,
        ?string $targetType = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): int {
        ['clause' => $clause, 'params' => $params] = $this->filterClause(
            '', $action, $userId, $targetType, $search, $dateFrom, $dateTo,
        );

        return $this->db->fetchInt('SELECT COUNT(*) FROM audit_logs' . $clause, $params);
    }

    /**
     * Distinct target_type values on record, for the filter dropdown.
     *
     * @return list<string>
     */
    public function distinctTargetTypes(): array
    {
        return array_column($this->db->fetchAll(
            'SELECT DISTINCT target_type FROM audit_logs WHERE target_type IS NOT NULL ORDER BY target_type ASC',
        ), 'target_type');
    }

    /**
     * The audit trail relevant to one patient, for the Patient Detail
     * page's Audit tab - not just target_type='patient' rows (which would
     * only ever be MPI registration and portal-access events), but every
     * encounter-scoped event belonging to one of THIS patient's
     * encounters too: encounter admit/discharge/override
     * (target_type='encounter') and charge/payment posting
     * (target_type='consumption_ledger'/'receivable_payment') are all
     * logged keyed by encounter_id, not patient_id, because that is what
     * BillingService/EncounterService/BillingController actually hold at
     * the point they call record(). Missing those would make this tab
     * materially incomplete, not just narrower.
     *
     * $actorUserId scopes to one actor's own entries, exactly like
     * forTarget()'s own parameter - how audit.view_own is enforced here.
     *
     * @return list<array<string, mixed>>
     */
    public function forPatient(int $patientId, int $limit = 50, ?int $actorUserId = null): array
    {
        // Two placeholders bound to the same patient id, not one reused:
        // PDO with ATTR_EMULATE_PREPARES off (see bin/check-sql.php)
        // allows a named placeholder only once per statement, and :pid
        // appears in both the direct target check and the subquery below.
        $actorClause = $actorUserId !== null ? ' AND a.user_id = :uid' : '';
        $params = ['pid1' => $patientId, 'pid2' => $patientId, 'limit' => $limit];

        if ($actorUserId !== null) {
            $params['uid'] = $actorUserId;
        }

        return $this->db->fetchAll(
            "SELECT a.*, INET6_NTOA(a.ip_address) AS ip_text, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE (
                 (a.target_type = 'patient' AND a.target_id = :pid1)
                 OR (
                     a.target_type IN ('encounter', 'consumption_ledger', 'receivable_payment')
                     AND a.target_id IN (SELECT id FROM encounters WHERE patient_id = :pid2)
                 )
             ){$actorClause}
             ORDER BY a.id DESC
             LIMIT :limit",
            $params,
        );
    }

    /** History for one record, shown on its detail page. */
    /**
     * $actorUserId, when given, scopes to that one actor's own entries -
     * how audit.view_own (migration 007) is enforced: a role holding
     * that permission instead of audit.view passes the signed-in user's
     * own id here, never trusting a caller-supplied filter, so "own
     * entries only" is true at the query itself rather than a display
     * choice layered on top of the full trail.
     */
    public function forTarget(string $targetType, int $targetId, int $limit = 30, ?int $actorUserId = null): array
    {
        $clause = $actorUserId !== null ? ' AND a.user_id = :uid' : '';
        $params = ['t' => $targetType, 'id' => $targetId, 'limit' => $limit];

        if ($actorUserId !== null) {
            $params['uid'] = $actorUserId;
        }

        return $this->db->fetchAll(
            'SELECT a.*, INET6_NTOA(a.ip_address) AS ip_text, u.full_name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.target_type = :t AND a.target_id = :id' . $clause . '
             ORDER BY a.id DESC
             LIMIT :limit',
            $params,
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
