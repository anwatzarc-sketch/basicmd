<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\Locale;
use Aster\Domain\Enum\PaymentChannel;

/**
 * A transfer destination and its instructions, as shown at checkout.
 *
 * This is what stands in for a gateway integration: the patient reads the
 * account details, moves the money themselves, and uploads evidence.
 */
final readonly class PaymentMethod
{
    public function __construct(
        public int $id,
        public PaymentChannel $channel,
        public string $provider,
        public ?string $providerAm,
        public ?string $accountName,
        public ?string $accountNumber,
        public ?string $branch,
        public ?string $logoPath,
        public ?string $instructions,
        public ?string $instructionsAm,
        public bool $requiresProof,
        public bool $isActive,
        public int $sortOrder,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:             (int) $row['id'],
            channel:        PaymentChannel::from((string) ($row['channel'] ?? 'bank_transfer')),
            provider:       (string) $row['provider'],
            providerAm:     self::nullableString($row['provider_am'] ?? null),
            accountName:    self::nullableString($row['account_name'] ?? null),
            accountNumber:  self::nullableString($row['account_number'] ?? null),
            branch:         self::nullableString($row['branch'] ?? null),
            logoPath:       self::nullableString($row['logo_path'] ?? null),
            instructions:   self::nullableString($row['instructions'] ?? null),
            instructionsAm: self::nullableString($row['instructions_am'] ?? null),
            requiresProof:  (bool) ($row['requires_proof'] ?? true),
            isActive:       ($row['status'] ?? 'active') === 'active',
            sortOrder:      (int) ($row['sort_order'] ?? 0),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function name(Locale $locale): string
    {
        return $locale === Locale::AM && $this->providerAm !== null ? $this->providerAm : $this->provider;
    }

    public function howTo(Locale $locale): ?string
    {
        return $locale === Locale::AM && $this->instructionsAm !== null
            ? $this->instructionsAm
            : $this->instructions;
    }

    public function hasAccountDetails(): bool
    {
        return $this->accountNumber !== null && $this->channel->showsAccountNumber();
    }

    /** Whether the patient is expected to upload a slip for this method. */
    public function expectsProof(): bool
    {
        return $this->requiresProof && $this->channel->expectsProof();
    }
}
