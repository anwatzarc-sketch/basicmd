<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Mail;

use RuntimeException;

/**
 * Delivery failed.
 *
 * Always caught by the queue worker, which decides between a retry and a
 * permanent failure. It never reaches a web request, because no web request
 * sends mail synchronously.
 */
final class MailException extends RuntimeException
{
    /**
     * Whether retrying could plausibly succeed.
     *
     * A refused recipient or a malformed address will fail identically every
     * time; retrying it four more times just delays the failure and burns
     * sender reputation. Connection and timeout errors are worth another go.
     */
    public function isRetryable(): bool
    {
        $message = strtolower($this->getMessage());

        $permanent = [
            'invalid address',
            'recipient address rejected',
            'user unknown',
            'no such user',
            'mailbox unavailable',
            'domain not found',
            '550',
            '553',
        ];

        foreach ($permanent as $marker) {
            if (str_contains($message, $marker)) {
                return false;
            }
        }

        return true;
    }
}
