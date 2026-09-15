<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Application\DTO\BookingRequest;
use Aster\Domain\Entity\Appointment;
use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\BookingSource;
use Aster\Domain\Enum\QueueTier;
use Aster\Domain\Enum\TimeSlot;
use Aster\Domain\Exception\BookingException;
use Aster\Domain\Exception\ValidationException;
use Aster\Domain\ValueObject\BookingReference;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Logger;
use DateTimeImmutable;
use RuntimeException;

/**
 * The booking engine.
 *
 * Owns the whole path from a validated request to a persisted appointment:
 * business-rule checks, pricing, reference generation, the capacity-guarded
 * insert, and the notifications that follow.
 *
 * Notifications are queued AFTER the transaction commits. Enqueuing inside it
 * would mean a rolled-back booking could still have sent a confirmation, and
 * a patient holding a confirmation for an appointment that does not exist is
 * far worse than one that arrives a minute late.
 */
final readonly class BookingService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private DoctorRepository $doctors,
        private ServiceRepository $services,
        private PackageRepository $packages,
        private PricingService $pricing,
        private NotificationService $notifications,
        private AuditLogger $audit,
        private Config $config,
        private Logger $logger,
    ) {
    }

    /**
     * Create a booking.
     *
     * @throws ValidationException on malformed input
     * @throws BookingException    on a business-rule refusal (full, too soon)
     */
    public function book(BookingRequest $request): Appointment
    {
        $this->assertDateIsBookable($request->date, $request->timeSlot);

        $service = $request->serviceId !== null ? $this->services->findById($request->serviceId) : null;
        $package = $request->packageId !== null ? $this->packages->findById($request->packageId) : null;

        if ($service === null && $package === null) {
            throw ValidationException::single('service_id', 'Please choose a service or a health package.');
        }

        // A deactivated doctor could still be submitted by a stale form or a
        // hand-crafted POST, so the id is re-validated rather than trusted.
        $doctor = null;

        if ($request->doctorId !== null) {
            $doctor = $this->doctors->findById($request->doctorId);

            if ($doctor === null || !$doctor->isBookable()) {
                throw BookingException::doctorUnavailable();
            }
        }

        $quote = $this->pricing->quote($service, $package, $doctor, $request->queueTier);

        $reference = $this->generateUniqueReference();

        $appointmentId = $this->appointments->createWithCapacityCheck([
            'booking_ref'      => $reference->value,
            'patient_name'     => $request->patientName,
            'patient_phone'    => $request->patientPhone->e164,
            'patient_email'    => $request->patientEmail,
            'patient_notes'    => $request->notes,
            'service_id'       => $service?->id,
            'package_id'       => $package?->id,
            'doctor_id'        => $doctor?->id,
            'appointment_date' => $request->date->format('Y-m-d'),
            'time_slot'        => $request->timeSlot->value,
            'queue_tier'       => $request->queueTier->value,
            'status'           => AppointmentStatus::PENDING->value,
            'payment_status'   => $quote->isFree() ? 'waived' : 'unpaid',
            'base_amount'      => $quote->base->toDatabase(),
            'surcharge_amount' => $quote->surcharge->toDatabase(),
            'total_amount'     => $quote->total->toDatabase(),
            'source'           => $request->source->value,
            'locale'           => $request->locale->value,
            'handled_by'       => $request->staffUserId,
            'ip_address'       => $request->ipBinary,
        ]);

        $appointment = $this->appointments->findById($appointmentId);

        if ($appointment === null) {
            // The insert reported success, so a missing row means something
            // is badly wrong with the connection or replication.
            throw new RuntimeException('Appointment vanished immediately after creation.');
        }

        $this->audit->record(
            AuditLogger::APPOINTMENT_CREATED,
            'appointment',
            $appointment->id,
            sprintf(
                'Booking %s created via %s for %s',
                $reference->value,
                $request->source->value,
                $appointment->date->format('Y-m-d'),
            ),
        );

        $this->dispatchBookingNotifications($appointment);

        return $appointment;
    }

    /** Queued after commit; a mail failure must never undo a saved booking. */
    private function dispatchBookingNotifications(Appointment $appointment): void
    {
        try {
            $this->notifications->bookingConfirmation($appointment);
            $this->notifications->notifyStaffNewBooking($appointment);
        } catch (\Throwable $e) {
            $this->logger->error('Booking notification enqueue failed', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enforce the lead time, booking horizon and closing days.
     *
     * @throws BookingException
     */
    private function assertDateIsBookable(DateTimeImmutable $date, TimeSlot $slot): void
    {
        $tz  = $this->config->timezone;
        $now = new DateTimeImmutable('now', $tz);

        $slotStart = $slot->startsAt($date, $tz);

        if ($slotStart < $now) {
            throw BookingException::dateInPast();
        }

        $leadHours = $this->config->bookingLeadHours();

        if ($slotStart < $now->modify("+{$leadHours} hours")) {
            throw BookingException::tooSoon($leadHours);
        }

        $horizonDays = $this->config->bookingHorizonDays();

        if ($date > $now->modify("+{$horizonDays} days")) {
            throw BookingException::tooFarAhead($horizonDays);
        }

        // The clinic is closed on Sundays (PHP 'w': 0 = Sunday).
        if ((int) $date->format('w') === 0) {
            throw BookingException::clinicClosed();
        }
    }

    /**
     * Mint a reference that is not already taken.
     *
     * With a 25-character alphabet over 8 positions the space is ~1.5e11, so
     * a collision is vanishingly unlikely - but "unlikely" is not "never",
     * and a duplicate would surface as a confusing unique-key error during a
     * patient's booking. Ten attempts costs nothing and removes the case.
     */
    private function generateUniqueReference(): BookingReference
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = BookingReference::generate();

            if (!$this->appointments->referenceExists($reference->value)) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique booking reference after 10 attempts.');
    }

    // -----------------------------------------------------------------
    //  Lifecycle transitions
    // -----------------------------------------------------------------

    /**
     * Move an appointment to a new status and notify the patient.
     *
     * @throws BookingException on an illegal transition
     */
    public function changeStatus(
        int $appointmentId,
        AppointmentStatus $target,
        ?int $actorId,
        ?string $reason = null,
    ): Appointment {
        $previous = $this->appointments->transitionStatus($appointmentId, $target, $actorId, $reason);

        $appointment = $this->appointments->findById($appointmentId);

        if ($appointment === null) {
            throw BookingException::notFound();
        }

        // transitionStatus() is idempotent, so a no-op click produces no
        // audit noise and no duplicate email.
        if ($previous === $target) {
            return $appointment;
        }

        $this->audit->record(
            AuditLogger::APPOINTMENT_STATUS,
            'appointment',
            $appointment->id,
            sprintf('%s -> %s', $previous->label(), $target->label()),
            ['before' => ['status' => $previous->value], 'after' => ['status' => $target->value]],
        );

        try {
            match ($target) {
                AppointmentStatus::CONFIRMED => $this->notifications->appointmentConfirmed($appointment),
                AppointmentStatus::CANCELLED => $this->notifications->appointmentCancelled($appointment, $reason),
                // Completion triggers no immediate mail; the follow-up goes
                // out later, on its own schedule, from the cron worker.
                default => null,
            };
        } catch (\Throwable $e) {
            $this->logger->error('Status-change notification failed', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
        }

        return $appointment;
    }

    /**
     * Patient-initiated cancellation from the public lookup page.
     *
     * Requires both the reference and the phone number, and refuses once the
     * appointment is within two hours.
     *
     * @throws BookingException
     */
    public function cancelAsPatient(BookingReference $reference, string $phoneE164, ?string $reason): Appointment
    {
        $appointment = $this->appointments->findForPatient($reference, $phoneE164);

        if ($appointment === null) {
            throw BookingException::notFound();
        }

        if ($appointment->status->isTerminal()) {
            throw BookingException::alreadyFinalised($appointment->status);
        }

        if (!$appointment->isPatientCancellable($this->config->timezone)) {
            throw BookingException::notCancellable();
        }

        return $this->changeStatus(
            $appointment->id,
            AppointmentStatus::CANCELLED,
            null,
            $reason ?? 'Cancelled by patient',
        );
    }

    // -----------------------------------------------------------------
    //  Availability, for the booking form
    // -----------------------------------------------------------------

    /**
     * Seats remaining per slot on a date.
     *
     * @return array<string, int>
     */
    public function availability(string $date, ?int $doctorId = null): array
    {
        $availability = $doctorId !== null
            ? $this->appointments->slotAvailability($doctorId, $date)
            : $this->appointments->aggregateAvailability($date);

        // Zero out slots whose start has already passed the lead-time cutoff,
        // so the form never offers a time the service layer would reject.
        $tz     = $this->config->timezone;
        $cutoff = (new DateTimeImmutable('now', $tz))->modify('+' . $this->config->bookingLeadHours() . ' hours');
        $day    = new DateTimeImmutable($date, $tz);

        foreach (TimeSlot::all() as $slot) {
            if ($slot->startsAt($day, $tz) < $cutoff) {
                $availability[$slot->value] = 0;
            }
        }

        return $availability;
    }

    /**
     * Which dates in the booking window still have capacity.
     *
     * @return array<string, bool>
     */
    public function calendar(?int $doctorId = null): array
    {
        $tz   = $this->config->timezone;
        $from = new DateTimeImmutable('today', $tz);
        $to   = $from->modify('+' . $this->config->bookingHorizonDays() . ' days');

        $calendar = $this->appointments->availabilityCalendar(
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $doctorId,
        );

        foreach ($calendar as $date => $hasCapacity) {
            if (!$hasCapacity) {
                continue;
            }

            // Sundays are closed, and a day whose every slot has passed the
            // lead-time cutoff is effectively full.
            if ((int) (new DateTimeImmutable($date, $tz))->format('w') === 0) {
                $calendar[$date] = false;
                continue;
            }

            $calendar[$date] = array_sum($this->availability($date, $doctorId)) > 0;
        }

        return $calendar;
    }

    public function quoteFor(?int $serviceId, ?int $packageId, ?int $doctorId, QueueTier $tier): \Aster\Application\DTO\PriceQuote
    {
        return $this->pricing->quote(
            $serviceId !== null ? $this->services->findById($serviceId) : null,
            $packageId !== null ? $this->packages->findById($packageId) : null,
            $doctorId !== null ? $this->doctors->findById($doctorId) : null,
            $tier,
        );
    }

    public function source(): BookingSource
    {
        return BookingSource::WEB;
    }
}
