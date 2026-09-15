<?php

declare(strict_types=1);

namespace Aster\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * An exception that carries an intended HTTP status.
 *
 * The messages here are deliberately bland and safe to show a visitor. The
 * detailed reason goes to the logger, never to the response body - an error
 * page on a medical site must not reveal whether a given booking reference
 * exists, which table a query touched, or which file threw.
 */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function notFound(string $message = 'The page you requested could not be found.'): self
    {
        return new self(404, $message);
    }

    public static function methodNotAllowed(string $message = 'That action is not allowed here.'): self
    {
        return new self(405, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to view this page.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Please sign in to continue.'): self
    {
        return new self(401, $message);
    }

    /** CSRF token missing, stale or mismatched. */
    public static function tokenMismatch(
        string $message = 'Your session expired for security reasons. Please try again.',
    ): self {
        return new self(419, $message);
    }

    public static function tooManyRequests(int $retryAfterSeconds = 60): self
    {
        return new self(
            429,
            'Too many attempts. Please wait a moment and try again.',
            ['Retry-After' => (string) $retryAfterSeconds],
        );
    }

    public static function payloadTooLarge(string $message = 'That file is too large to upload.'): self
    {
        return new self(413, $message);
    }

    public static function serverError(string $message = 'Something went wrong on our end.'): self
    {
        return new self(500, $message);
    }

    public static function serviceUnavailable(string $message = 'The service is temporarily unavailable.'): self
    {
        return new self(503, $message);
    }
}
