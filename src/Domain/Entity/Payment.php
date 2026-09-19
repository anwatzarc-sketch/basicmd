<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\PaymentKind;
use MediCareMini\Domain\Enum\ProofStatus;
use MediCareMini\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * One proof-of-payment submission against an appointment.
 *
 * This is the record a Finance officer reviews: the amount the patient says
 * they sent, the transfer reference, and the uploaded slip or mobile-banking
 * screenshot. `proofPath` is relative to the private uploads directory and is
 * never a URL - the file is only ever streamed through an authenticated
 * controller, so it cannot be hot-linked or indexed.
 */
final readonly class Payment
{
    public function __construct(
        public int $id,
        public int $appointmentId,
        public ?int $paymentMethodId,
        public PaymentKind $kind,
        public Money $amount,
        public ?string $payerName,
        public ?string $transferRef,
        public ?DateTimeImmutable $transferredAt,
        public ?string $proofPath,
        public ?string $proofOriginalName,
        public ?string $proofMime,
        public ?int $proofSize,
        public ?string $proofSha256,
        public ProofStatus $status,
        public ?int $verifiedBy,
        public ?DateTimeImmutable $verifiedAt,
        public ?string $rejectionReason,
        public ?string $adminNote,
        public DateTimeImmutable $createdAt,
        // --- Denormalised labels from the repository JOIN ---
        public ?string $methodLabel = null,
        public ?string $verifierName = null,
        public ?string $bookingRef = null,
        public ?string $patientName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                (int) $row['id'],
            appointmentId:     (int) $row['appointment_id'],
            paymentMethodId:   self::nullableInt($row['payment_method_id'] ?? null),
            kind:              PaymentKind::from((string) ($row['kind'] ?? 'full')),
            amount:            Money::fromDatabase($row['amount'] ?? 0, (string) ($row['currency'] ?? 'ETB')),
            payerName:         self::nullableString($row['payer_name'] ?? null),
            transferRef:       self::nullableString($row['transfer_ref'] ?? null),
            transferredAt:     self::toDate($row['transferred_at'] ?? null),
            proofPath:         self::nullableString($row['proof_path'] ?? null),
            proofOriginalName: self::nullableString($row['proof_original'] ?? null),
            proofMime:         self::nullableString($row['proof_mime'] ?? null),
            proofSize:         self::nullableInt($row['proof_size'] ?? null),
            proofSha256:       self::nullableString($row['proof_sha256'] ?? null),
            status:            ProofStatus::from((string) ($row['status'] ?? 'awaiting_proof')),
            verifiedBy:        self::nullableInt($row['verified_by'] ?? null),
            verifiedAt:        self::toDate($row['verified_at'] ?? null),
            rejectionReason:   self::nullableString($row['rejection_reason'] ?? null),
            adminNote:         self::nullableString($row['admin_note'] ?? null),
            createdAt:         self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            methodLabel:       self::nullableString($row['method_label'] ?? null),
            verifierName:      self::nullableString($row['verifier_name'] ?? null),
            bookingRef:        self::nullableString($row['booking_ref'] ?? null),
            patientName:       self::nullableString($row['patient_name'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value . ' UTC') : null;
    }

    public function hasProofFile(): bool
    {
        return $this->proofPath !== null;
    }

    public function isPendingReview(): bool
    {
        return $this->status->isPendingReview();
    }

    public function isVerified(): bool
    {
        return $this->status === ProofStatus::VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === ProofStatus::REJECTED;
    }

    /** True when the uploaded proof is an image that can be previewed inline. */
    public function isImageProof(): bool
    {
        return $this->proofMime !== null && str_starts_with($this->proofMime, 'image/');
    }

    public function isPdfProof(): bool
    {
        return $this->proofMime === 'application/pdf';
    }

    public function proofSizeLabel(): string
    {
        if ($this->proofSize === null) {
            return '';
        }

        return $this->proofSize >= 1048576
            ? round($this->proofSize / 1048576, 1) . ' MB'
            : max(1, (int) round($this->proofSize / 1024)) . ' KB';
    }

    /** Short digest shown in the admin table for duplicate-slip spotting. */
    public function shortHash(): string
    {
        return $this->proofSha256 !== null ? substr($this->proofSha256, 0, 10) : '';
    }
}
