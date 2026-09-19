<?php

declare(strict_types=1);

namespace MediCareMini\Application\Service;

use MediCareMini\Domain\Entity\Doctor;
use MediCareMini\Domain\Entity\HealthPackage;
use MediCareMini\Domain\Entity\MedicalService;
use MediCareMini\Domain\Enum\QueueTier;
use MediCareMini\Application\DTO\PriceQuote;
use MediCareMini\Domain\ValueObject\Money;

/**
 * Works out what a booking costs.
 *
 * Isolated from BookingService so the quote shown on the booking form and the
 * amount actually written to the database come from one implementation. When
 * those drift, patients transfer the wrong figure and Finance spends its day
 * reconciling by hand.
 *
 * Pricing rules, in order:
 *   1. A health package price overrides everything - it is a fixed product.
 *   2. Otherwise the doctor's consultation fee, if they set one.
 *   3. Otherwise the service list price.
 *   4. Express queue adds the configured surcharge to whichever base applied.
 */
final readonly class PricingService
{
    public function __construct(private float $expressSurchargeRate)
    {
    }

    public function quote(
        ?MedicalService $service,
        ?HealthPackage $package,
        ?Doctor $doctor,
        QueueTier $tier,
    ): PriceQuote {
        $base = $this->baseAmount($service, $package, $doctor);

        $surcharge = $tier->appliesSurcharge() && $base->isPositive()
            ? $base->multiply($this->expressSurchargeRate)
            : Money::zero();

        $total = $base->add($surcharge);

        // Only packages take a deposit. A consultation is small enough that
        // splitting it into two transfers costs more in admin than it secures.
        $deposit = $package !== null && $package->requiresDeposit()
            ? $total->multiply($package->depositRate)
            : $total;

        return new PriceQuote(
            base:         $base,
            surcharge:    $surcharge,
            total:        $total,
            depositDue:   $deposit,
            requiresDeposit: $package !== null && $package->requiresDeposit(),
            surchargeRate:   $this->expressSurchargeRate,
        );
    }

    private function baseAmount(?MedicalService $service, ?HealthPackage $package, ?Doctor $doctor): Money
    {
        if ($package !== null) {
            return $package->price;
        }

        // A specialist's own fee supersedes the generic service price - a
        // cardiology consult with the head of department is not the same
        // product as the catalogue entry.
        if ($doctor !== null && $doctor->consultationFee->isPositive()) {
            return $doctor->consultationFee;
        }

        return $service?->price ?? Money::zero();
    }

    public function surchargeRate(): float
    {
        return $this->expressSurchargeRate;
    }

    /** "20%", for the booking form label. */
    public function surchargeLabel(): string
    {
        return rtrim(rtrim(number_format($this->expressSurchargeRate * 100, 1, '.', ''), '0'), '.') . '%';
    }
}
