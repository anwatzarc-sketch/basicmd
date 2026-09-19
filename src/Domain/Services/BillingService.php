<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Services;

use MediCareMini\Domain\Enum\EncounterStatus;
use MediCareMini\Domain\Enum\FinancialClearanceStatus;
use MediCareMini\Domain\Exception\EncounterException;
use MediCareMini\Domain\Exception\UnsettledBalanceException;
use MediCareMini\Domain\Repository\AuditLoggerInterface;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\LedgerRepositoryInterface;
use MediCareMini\Domain\Repository\ReceivablePaymentRepositoryInterface;
use MediCareMini\Domain\Repository\StaffDirectoryInterface;
use MediCareMini\Domain\Repository\TransactionManagerInterface;
use MediCareMini\Domain\Repository\WardLocationRepositoryInterface;
use MediCareMini\Domain\ValueObject\Balance;
use DateTimeImmutable;

/**
 * Dynamic receivable balance, financial clearance, and the discharge gate
 * (FRS 8.3).
 *
 * The invariant this whole class exists to protect:
 *
 *     ReceivableBalance = SUM(ledger.total_cost) - SUM(payments.amount_paid)
 *
 * computed fresh from the itemized rows on every call, never read from a
 * mutable stored total - there is no such column anywhere in this schema
 * for exactly that reason.
 */
final readonly class BillingService
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
        private ReceivablePaymentRepositoryInterface $payments,
        private EncounterRepositoryInterface $encounters,
        private WardLocationRepositoryInterface $wardLocations,
        private StaffDirectoryInterface $staff,
        private TransactionManagerInterface $transactions,
        private AuditLoggerInterface $audit,
    ) {
    }

    /**
     * FRS's own signature is `float`; the calculation itself happens in
     * integer minor units (Balance) and only converts to float at this
     * return boundary - comparing money as float is exactly how a 0.01
     * residue would wrongly block or wrongly clear a discharge.
     */
    public function calculateReceivableBalance(int $encounterId): float
    {
        return $this->balance($encounterId)->toMajor();
    }

    private function balance(int $encounterId): Balance
    {
        $charges = $this->ledger->sumForEncounter($encounterId);
        $paid    = $this->payments->sumForEncounter($encounterId);

        return $charges->subtract($paid);
    }

    /**
     * True when ReceivableBalance <= 0.00 OR the encounter's financial
     * clearance status is OVERRIDDEN (FRS 8.3, verbatim).
     */
    public function verifyFinancialClearance(int $encounterId): bool
    {
        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null) {
            throw EncounterException::encounterNotFound();
        }

        if ($encounter->financialClearanceStatus === FinancialClearanceStatus::OVERRIDDEN) {
            return true;
        }

        return $this->balance($encounterId)->isSettledOrCredit();
    }

    /**
     * Discharge (IPD) or close (OPD/ER) an encounter, gated on financial
     * clearance.
     *
     * @throws UnsettledBalanceException when the balance is unsettled and
     *                                   no valid override applies
     */
    public function processDischargeOrClosure(int $encounterId, int $actorUserId): void
    {
        if (!$this->staff->isActiveStaffUser($actorUserId)) {
            throw EncounterException::invalidActor();
        }

        $this->transactions->transactional(function () use ($encounterId, $actorUserId): void {
            // Locked BEFORE the balance is read, so a concurrent ledger or
            // payment insert against this same encounter cannot race the
            // decision made here - the same "lock first, decide after"
            // discipline EncounterService::upgradeOpdToIpd() uses for a bed.
            $encounter = $this->encounters->findByIdForUpdate($encounterId);

            if ($encounter === null) {
                throw EncounterException::encounterNotFound();
            }

            if ($encounter->status->isTerminal()) {
                throw EncounterException::notEligibleForAdmission($encounter->status);
            }

            $balance = $this->balance($encounterId);
            $cleared = $encounter->financialClearanceStatus === FinancialClearanceStatus::OVERRIDDEN
                || $balance->isSettledOrCredit();

            if (!$cleared) {
                // FIN-002/AC-DIS-01/AC-DIS-02: never released, never closed,
                // while a genuine debt remains and no override was applied.
                throw new UnsettledBalanceException($balance, $encounterId);
            }

            // FIN-005/AC-DIS-05: release the bed as part of the SAME
            // transaction as the status change - a failed discharge above
            // this point has touched nothing, so FIN-008 (a failed
            // discharge must not release the bed) holds by construction,
            // not by a separate check.
            if ($encounter->currentLocationId !== null) {
                $this->wardLocations->markAvailable($encounter->currentLocationId);
            }

            $this->encounters->update($encounterId, [
                'status'         => EncounterStatus::DISCHARGED->value,
                'discharged_at'  => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $this->audit->record(
                AuditLoggerInterface::ENCOUNTER_DISCHARGED,
                'encounter',
                $encounterId,
                sprintf(
                    'Discharged %s (balance %s, clearance %s) by user #%d',
                    $encounter->patientVisitNumber->value,
                    $balance->format(),
                    $encounter->financialClearanceStatus === FinancialClearanceStatus::OVERRIDDEN ? 'OVERRIDDEN' : 'settled',
                    $actorUserId,
                ),
            );
        });
    }

    /**
     * Apply an authorised override, so a subsequent discharge attempt
     * clears even with an outstanding balance. A separate method (and, in
     * the presentation layer, a separate, more tightly held permission)
     * from processDischargeOrClosure() itself - FIN-003/FIN-004: the
     * override is the thing that needs authorising, not the discharge
     * call that merely reads an already-applied one.
     */
    public function applyOverride(int $encounterId, int $actorUserId, string $reason): void
    {
        if (!$this->staff->isActiveStaffUser($actorUserId)) {
            throw EncounterException::invalidActor();
        }

        $encounter = $this->encounters->findById($encounterId);

        if ($encounter === null) {
            throw EncounterException::encounterNotFound();
        }

        $this->encounters->update($encounterId, [
            'financial_clearance_status' => FinancialClearanceStatus::OVERRIDDEN->value,
        ]);

        $this->audit->record(
            AuditLoggerInterface::ENCOUNTER_DISCHARGED,
            'encounter',
            $encounterId,
            sprintf('Financial clearance overridden for %s by user #%d: %s', $encounter->patientVisitNumber->value, $actorUserId, $reason),
        );
    }
}
