<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Enum;

/**
 * Verification state of a single proof-of-payment submission.
 *
 *      awaiting_proof ──► submitted ──► verified
 *                             └───────► rejected ──► (patient re-uploads:
 *                                                     a NEW payments row)
 *
 * A rejected row is never reopened. The patient's corrected slip becomes its
 * own record, so the audit trail keeps both the rejection and the fix.
 */
enum ProofStatus: string
{
    case AWAITING_PROOF = 'awaiting_proof';
    case SUBMITTED      = 'submitted';
    case VERIFIED       = 'verified';
    case REJECTED       = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::AWAITING_PROOF => 'Awaiting Proof',
            self::SUBMITTED      => 'Pending Verification',
            self::VERIFIED       => 'Verified',
            self::REJECTED       => 'Rejected',
        };
    }

    public function translationKey(): string
    {
        return 'proof_status.' . $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AWAITING_PROOF => 'bg-slate-100 text-slate-700 border-slate-200',
            self::SUBMITTED      => 'bg-amber-100 text-amber-800 border-amber-200',
            self::VERIFIED       => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::REJECTED       => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AWAITING_PROOF => [self::SUBMITTED],
            self::SUBMITTED      => [self::VERIFIED, self::REJECTED],
            self::VERIFIED, self::REJECTED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Sitting in the Finance review queue. */
    public function isPendingReview(): bool
    {
        return $this === self::SUBMITTED;
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
