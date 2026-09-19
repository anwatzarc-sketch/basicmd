<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\Enum\PaymentChannel;

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
        public ?string $providerOm,
        public ?string $accountName,
        public ?string $accountNumber,
        public ?string $branch,
        public ?string $logoPath,
        public ?string $instructions,
        public ?string $instructionsAm,
        public ?string $instructionsOm,
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
            providerOm:     self::nullableString($row['provider_om'] ?? null),
            accountName:    self::nullableString($row['account_name'] ?? null),
            accountNumber:  self::nullableString($row['account_number'] ?? null),
            branch:         self::nullableString($row['branch'] ?? null),
            logoPath:       self::nullableString($row['logo_path'] ?? null),
            instructions:   self::nullableString($row['instructions'] ?? null),
            instructionsAm: self::nullableString($row['instructions_am'] ?? null),
            instructionsOm: self::nullableString($row['instructions_om'] ?? null),
            requiresProof:  (bool) ($row['requires_proof'] ?? true),
            isActive:       ($row['status'] ?? 'active') === 'active',
            sortOrder:      (int) ($row['sort_order'] ?? 0),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The translation for a locale, or null when there is not a usable one.
     *
     * English is the base column and therefore never has a translation of
     * its own; a locale whose column is NULL or empty also returns null so
     * the caller can fall back with `?? $base`. Deliberately never falls
     * back to the OTHER translation: untranslated transfer instructions
     * degrade to readable English, not to Ge'ez script.
     */
    private function translation(Locale $locale, ?string $amharic, ?string $oromo): ?string
    {
        $value = match ($locale) {
            Locale::EN => null,
            Locale::AM => $amharic,
            Locale::OM => $oromo,
        };

        return $value !== null && $value !== '' ? $value : null;
    }

    public function name(Locale $locale): string
    {
        return $this->translation($locale, $this->providerAm, $this->providerOm) ?? $this->provider;
    }

    public function howTo(Locale $locale): ?string
    {
        return $this->translation($locale, $this->instructionsAm, $this->instructionsOm)
            ?? $this->instructions;
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
