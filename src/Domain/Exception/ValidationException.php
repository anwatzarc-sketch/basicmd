<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Exception;

use RuntimeException;

/**
 * Field-level validation failures, collected rather than thrown one at a time.
 *
 * A patient filling in a nine-field booking form should be told everything
 * that is wrong in one pass, not sent round the loop once per mistake.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors field => messages */
    public function __construct(
        private readonly array $errors,
        string $message = 'Please correct the highlighted fields.',
    ) {
        parent::__construct($message, 422);
    }

    /** @param array<string, string|list<string>> $errors */
    public static function withErrors(array $errors, string $message = 'Please correct the highlighted fields.'): self
    {
        $normalised = [];

        foreach ($errors as $field => $messages) {
            $normalised[$field] = is_array($messages) ? array_values($messages) : [$messages];
        }

        return new self($normalised, $message);
    }

    public static function single(string $field, string $message): self
    {
        return new self([$field => [$message]], $message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** First message per field, which is all a form needs to render. */
    public function flatErrors(): array
    {
        return array_map(static fn (array $messages): string => $messages[0] ?? '', $this->errors);
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /** @return list<string> Every message, flattened - for a summary banner. */
    public function all(): array
    {
        return array_merge(...array_values($this->errors)) ?: [];
    }
}
