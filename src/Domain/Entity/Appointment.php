<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\AppointmentStatus;
use Aster\Domain\Enum\BookingSource;
use Aster\Domain\Enum\Locale;
use Aster\Domain\Enum\PaymentStatus;
use Aster\Domain\Enum\QueueTier;
use Aster\Domain\Enum\TimeSlot;
use Aster\Domain\ValueObject\BookingReference;
use Aster\Domain\ValueObject\EthiopianDate;
use Aster\Domain\ValueObject\Money;
use Aster\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A booking.
 *
 * Carries denormalised display fields (doctorName, serviceName) populated by
 * the repository's JOIN. They exist so a list of 50 appointments is one query
 * rather than 150, and they are read-only labels - never a substitute for the
 * foreign keys when writing.
 */
final readonly class Appointment
{
    public function __construct(
        public int $id,
        public BookingReference $reference,
        public string $patientName,
        public PhoneNumber $patientPhone,
        public ?string $patientEmail,
        public ?string $patientNotes,
        public ?int $serviceId,
        public ?int $packageId,
        public ?int $doctorId,
        public DateTimeImmutable $date,
        public TimeSlot $timeSlot,
        public QueueTier $queueTier,
        public AppointmentStatus $status,
        public PaymentStatus $paymentStatus,
        public Money $baseAmount,
        public Money $surchargeAmount,
        public Money $totalAmount,
        public Money $amountPaid,
        public ?string $paymentRef,
        public BookingSource $source,
        public Locale $locale,
        public ?DateTimeImmutable $confirmedAt,
        public ?DateTimeImmutable $completedAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?string $cancelReason,
        public ?DateTimeImmutable $reminderSentAt,
        public ?DateTimeImmutable $followupSentAt,
        public DateTimeImmutable $createdAt,
        // --- Denormalised labels from the repository JOIN ---
        public ?string $doctorName = null,
        public ?string $serviceName = null,
        public ?string $packageName = null,
        public ?string $handlerName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            reference:       BookingReference::fromString((string) $row['booking_ref']),
            patientName:     (string) $row['patient_name'],
            patientPhone:    PhoneNumber::fromString((string) $row['patient_phone']),
            patientEmail:    self::nullableString($row['patient_email'] ?? null),
            patientNotes:    self::nullableString($row['patient_notes'] ?? null),
            serviceId:       self::nullableInt($row['service_id'] ?? null),
            packageId:       self::nullableInt($row['package_id'] ?? null),
            doctorId:        self::nullableInt($row['doctor_id'] ?? null),
            date:            new DateTimeImmutable((string) $row['appointment_date']),
            timeSlot:        TimeSlot::from((string) $row['time_slot']),
            queueTier:       QueueTier::from((string) ($row['queue_tier'] ?? 'standard')),
            status:          AppointmentStatus::from((string) $row['status']),
            paymentStatus:   PaymentStatus::from((string) ($row['payment_status'] ?? 'unpaid')),
            baseAmount:      Money::fromDatabase($row['base_amount'] ?? 0),
            surchargeAmount: Money::fromDatabase($row['surcharge_amount'] ?? 0),
            totalAmount:     Money::fromDatabase($row['total_amount'] ?? 0),
            amountPaid:      Money::fromDatabase($row['amount_paid'] ?? 0),
            paymentRef:      self::nullableString($row['payment_ref'] ?? null),
            source:          BookingSource::from((string) ($row['source'] ?? 'web')),
            locale:          Locale::from((string) ($row['locale'] ?? 'en')),
            confirmedAt:     self::toDate($row['confirmed_at'] ?? null),
            completedAt:     self::toDate($row['completed_at'] ?? null),
            cancelledAt:     self::toDate($row['cancelled_at'] ?? null),
            cancelReason:    self::nullableString($row['cancel_reason'] ?? null),
            reminderSentAt:  self::toDate($row['reminder_sent_at'] ?? null),
            followupSentAt:  self::toDate($row['followup_sent_at'] ?? null),
            createdAt:       self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            doctorName:      self::nullableString($row['doctor_name'] ?? null),
            serviceName:     self::nullableString($row['service_name'] ?? null),
            packageName:     self::nullableString($row['package_name'] ?? null),
            handlerName:     self::nullableString($row['handler_name'] ?? null),
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

    /** What the patient actually booked - package takes precedence. */
    public function subjectLabel(): string
    {
        return $this->packageName ?? $this->serviceName ?? 'General consultation';
    }

    public function doctorLabel(): string
    {
        return $this->doctorName ?? 'Any available doctor';
    }

    /** Outstanding balance after everything verified so far. */
    public function balanceDue(): Money
    {
        return $this->totalAmount->amountMinor > $this->amountPaid->amountMinor
            ? $this->totalAmount->subtract($this->amountPaid)
            : Money::zero();
    }

    public function isFullyPaid(): bool
    {
        return $this->totalAmount->isZero()
            || $this->amountPaid->amountMinor >= $this->totalAmount->amountMinor;
    }

    /** The clinic-local start instant of this appointment. */
    public function startsAt(DateTimeZone $tz): DateTimeImmutable
    {
        return $this->timeSlot->startsAt($this->date, $tz);
    }

    public function isPast(DateTimeZone $tz): bool
    {
        return $this->startsAt($tz) < new DateTimeImmutable('now', $tz);
    }

    public function isToday(DateTimeZone $tz): bool
    {
        return $this->date->format('Y-m-d') === (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    public function ethiopianDate(): EthiopianDate
    {
        return EthiopianDate::fromGregorian($this->date);
    }

    /**
     * Whether a patient may still cancel this booking themselves.
     * Cancellation closes two hours before the slot so the front desk is not
     * re-filling a room the patient is already walking into.
     */
    public function isPatientCancellable(DateTimeZone $tz): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        return $this->startsAt($tz) > (new DateTimeImmutable('now', $tz))->modify('+2 hours');
    }

    public function canTransitionTo(AppointmentStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function hasEmail(): bool
    {
        return $this->patientEmail !== null;
    }

    public function isExpress(): bool
    {
        return $this->queueTier === QueueTier::EXPRESS;
    }
}
