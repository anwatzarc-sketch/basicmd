<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Exception;

use RuntimeException;

/** MPI errors - see PatientDeduplicationService. */
final class PatientException extends RuntimeException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message, 409);
    }

    /**
     * FRS 8.1 defines exact matching only and leaves conflict resolution
     * for an ambiguous case undefined. Two existing rows both exactly
     * matching the same lookup (e.g. a shared national_id that should
     * never happen, or a same-day duplicate submitted twice before the
     * advisory lock closed the window) must never be silently merged or
     * silently resolved to "whichever came first" - that is exactly the
     * kind of decision this exception forces into view instead.
     */
    public static function ambiguousMatch(string $matchedOn): self
    {
        return new self(
            "More than one existing patient matches on {$matchedOn}. This needs staff review before continuing.",
            'ambiguous_match',
        );
    }

    public static function notFound(): self
    {
        return new self('We could not find a patient with that identifier.', 'not_found');
    }
}
