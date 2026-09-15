<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\InquiryStatus;
use Aster\Domain\Enum\Locale;
use Aster\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

final readonly class ContactInquiry
{
    public function __construct(
        public int $id,
        public string $name,
        public PhoneNumber $phone,
        public ?string $email,
        public ?string $subject,
        public string $message,
        public InquiryStatus $status,
        public ?string $notes,
        public ?int $handledBy,
        public ?DateTimeImmutable $respondedAt,
        public Locale $locale,
        public DateTimeImmutable $createdAt,
        public ?string $handlerName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:          (int) $row['id'],
            name:        (string) $row['name'],
            phone:       PhoneNumber::fromString((string) $row['phone']),
            email:       self::nullableString($row['email'] ?? null),
            subject:     self::nullableString($row['subject'] ?? null),
            message:     (string) $row['message'],
            status:      InquiryStatus::from((string) ($row['status'] ?? 'unread')),
            notes:       self::nullableString($row['notes'] ?? null),
            handledBy:   isset($row['handled_by']) && $row['handled_by'] !== null ? (int) $row['handled_by'] : null,
            respondedAt: self::toDate($row['responded_at'] ?? null),
            locale:      Locale::from((string) ($row['locale'] ?? 'en')),
            createdAt:   self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            handlerName: self::nullableString($row['handler_name'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value . ' UTC') : null;
    }

    public function subjectLine(): string
    {
        return $this->subject ?? 'General enquiry';
    }

    /** Truncated message for the inbox list view. */
    public function preview(int $length = 120): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $this->message) ?? $this->message);

        return mb_strlen($flat) <= $length ? $flat : mb_substr($flat, 0, $length - 1) . "\u{2026}";
    }

    public function isReplyable(): bool
    {
        return $this->email !== null;
    }

    public function needsAttention(): bool
    {
        return $this->status->needsAttention();
    }
}
