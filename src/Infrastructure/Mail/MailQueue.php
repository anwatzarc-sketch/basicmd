<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Mail;

use MediCareMini\Infrastructure\Persistence\Database;
use MediCareMini\Infrastructure\Support\Env;
use MediCareMini\Infrastructure\Support\Logger;
use Throwable;

/**
 * Durable outbox.
 *
 * Web requests call enqueue() and return immediately. bin/queue-worker.php,
 * driven by cron, calls drain() to actually deliver.
 *
 * Claiming is the subtle part. Two workers running concurrently (a cron
 * overrun, say) must never both send the same message - a patient receiving
 * two identical confirmations looks broken. claimBatch() marks rows 'sending'
 * with a conditional UPDATE, so only the worker whose UPDATE matched owns
 * them. A worker that crashes mid-send leaves rows stuck in 'sending';
 * releaseStale() returns those to the queue after a grace period.
 */
final class MailQueue
{
    private const int STALE_LOCK_MINUTES = 15;

    public function __construct(
        private readonly Database $db,
        private readonly Mailer $mailer,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Add a message to the outbox.
     *
     * @param array<string, mixed> $context
     * @return int|null the outbox id, or null when there is no recipient
     */
    public function enqueue(
        string $mailable,
        ?string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $context = [],
    ): ?int {
        // A patient may book by phone alone; that is not an error, there is
        // simply nothing to send.
        if ($toEmail === null || $toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $this->db->execute(
                'INSERT INTO email_outbox
                    (mailable, to_email, to_name, subject, body_html, body_text,
                     context_json, related_type, related_id, max_attempts)
                 VALUES (:mailable, :email, :name, :subject, :html, :text,
                         :context, :rtype, :rid, :max)',
                [
                    'mailable' => $mailable,
                    'email'    => $toEmail,
                    'name'     => $toName,
                    'subject'  => mb_substr($subject, 0, 255),
                    'html'     => $htmlBody,
                    'text'     => $textBody ?? Mailer::htmlToText($htmlBody),
                    'context'  => $context === [] ? null : json_encode(
                        $context,
                        JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
                    ),
                    'rtype'    => $relatedType,
                    'rid'      => $relatedId,
                    'max'      => Env::int('QUEUE_MAX_ATTEMPTS', 5),
                ],
            );

            return $this->db->lastInsertId();
        } catch (Throwable $e) {
            // Queueing must not break the action that triggered it. A patient
            // who has just booked should not see an error because the outbox
            // insert failed - the booking itself is already committed.
            $this->logger->error('Failed to enqueue email', [
                'mailable' => $mailable,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send up to $batchSize queued messages.
     *
     * @return array{sent:int, failed:int, retried:int}
     */
    public function drain(?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? Env::int('QUEUE_BATCH_SIZE', 25);

        $this->releaseStale();

        $claimed = $this->claimBatch($batchSize);
        $stats   = ['sent' => 0, 'failed' => 0, 'retried' => 0];

        foreach ($claimed as $row) {
            try {
                $this->mailer->send(
                    (string) $row['to_email'],
                    $row['to_name'] !== null ? (string) $row['to_name'] : null,
                    (string) $row['subject'],
                    (string) $row['body_html'],
                    $row['body_text'] !== null ? (string) $row['body_text'] : null,
                );

                $this->markSent((int) $row['id']);
                $stats['sent']++;
            } catch (MailException $e) {
                $attempts    = (int) $row['attempts'];
                $maxAttempts = (int) $row['max_attempts'];

                if (!$e->isRetryable() || $attempts >= $maxAttempts) {
                    $this->markFailed((int) $row['id'], $e->getMessage());
                    $stats['failed']++;

                    $this->logger->error('Email permanently failed', [
                        'outbox_id' => $row['id'],
                        'mailable'  => $row['mailable'],
                        'attempts'  => $attempts,
                        'error'     => $e->getMessage(),
                    ]);
                } else {
                    $this->scheduleRetry((int) $row['id'], $attempts, $e->getMessage());
                    $stats['retried']++;
                }
            } catch (Throwable $e) {
                // Anything unexpected is treated as retryable rather than
                // losing the message outright.
                $this->scheduleRetry((int) $row['id'], (int) $row['attempts'], $e->getMessage());
                $stats['retried']++;

                $this->logger->exception($e, 'error', ['outbox_id' => $row['id']]);
            }
        }

        return $stats;
    }

    /**
     * Atomically claim a batch for this worker.
     *
     * A token is written into last_error, then the rows carrying it are read
     * back. Only the worker whose UPDATE actually matched a row will see its
     * token there, so no two workers can claim the same message even without
     * an explicit table lock.
     *
     * @return list<array<string, mixed>>
     */
    private function claimBatch(int $limit): array
    {
        $token = bin2hex(random_bytes(12));

        $this->db->execute(
            "UPDATE email_outbox
             SET status = 'sending', locked_at = UTC_TIMESTAMP(),
                 attempts = attempts + 1, last_error = :token
             WHERE status = 'queued' AND available_at <= UTC_TIMESTAMP()
             ORDER BY id ASC
             LIMIT {$limit}",
            ['token' => 'claim:' . $token],
        );

        return $this->db->fetchAll(
            "SELECT * FROM email_outbox
             WHERE status = 'sending' AND last_error = :token
             ORDER BY id ASC",
            ['token' => 'claim:' . $token],
        );
    }

    private function markSent(int $id): void
    {
        $this->db->execute(
            "UPDATE email_outbox
             SET status = 'sent', sent_at = UTC_TIMESTAMP(), locked_at = NULL, last_error = NULL
             WHERE id = :id",
            ['id' => $id],
        );
    }

    private function markFailed(int $id, string $error): void
    {
        $this->db->execute(
            "UPDATE email_outbox
             SET status = 'failed', locked_at = NULL, last_error = :error
             WHERE id = :id",
            ['error' => mb_substr($error, 0, 1000), 'id' => $id],
        );
    }

    /** Requeue with linear backoff: attempt N waits N * QUEUE_RETRY_BACKOFF_MIN. */
    private function scheduleRetry(int $id, int $attempts, string $error): void
    {
        $delayMinutes = max(1, $attempts) * Env::int('QUEUE_RETRY_BACKOFF_MIN', 5);

        $this->db->execute(
            "UPDATE email_outbox
             SET status = 'queued',
                 locked_at = NULL,
                 available_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :delay MINUTE),
                 last_error = :error
             WHERE id = :id",
            ['delay' => $delayMinutes, 'error' => mb_substr($error, 0, 1000), 'id' => $id],
        );
    }

    /** Return messages abandoned by a crashed worker to the queue. */
    private function releaseStale(): int
    {
        return $this->db->execute(
            "UPDATE email_outbox
             SET status = 'queued', locked_at = NULL
             WHERE status = 'sending'
               AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :mins MINUTE)",
            ['mins' => self::STALE_LOCK_MINUTES],
        );
    }

    /** @return array<string, int> status => count */
    public function stats(): array
    {
        return array_map('intval', $this->db->fetchPairs(
            'SELECT status, COUNT(*) FROM email_outbox GROUP BY status'
        ));
    }

    public function countQueued(): int
    {
        return $this->db->fetchInt("SELECT COUNT(*) FROM email_outbox WHERE status = 'queued'");
    }

    /** @return list<array<string, mixed>> Failed messages, for the admin view. */
    public function failures(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT id, mailable, to_email, subject, attempts, last_error, created_at
             FROM email_outbox
             WHERE status = 'failed'
             ORDER BY id DESC
             LIMIT :limit",
            ['limit' => $limit],
        );
    }

    /** Put a failed message back in the queue, attempt counter reset. */
    public function retry(int $id): bool
    {
        return $this->db->execute(
            "UPDATE email_outbox
             SET status = 'queued', attempts = 0, available_at = UTC_TIMESTAMP(), last_error = NULL
             WHERE id = :id AND status = 'failed'",
            ['id' => $id],
        ) > 0;
    }

    /** Delete delivered messages older than the retention window. */
    public function pruneSent(int $days = 30): int
    {
        return $this->db->execute(
            "DELETE FROM email_outbox
             WHERE status = 'sent' AND sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :d DAY)",
            ['d' => $days],
        );
    }
}
