<?php

declare(strict_types=1);

namespace MediCareMini\Application\DTO;

use MediCareMini\Domain\Enum\BookingSource;
use MediCareMini\Domain\Enum\Locale;
use MediCareMini\Domain\Enum\QueueTier;
use MediCareMini\Domain\Enum\TimeSlot;
use MediCareMini\Domain\Exception\ValidationException;
use MediCareMini\Domain\ValueObject\PhoneNumber;
use MediCareMini\Presentation\Http\Request;
use DateTimeImmutable;

/**
 * A validated booking request.
 *
 * Construction is the validation boundary: if you hold one of these, every
 * field has already been checked and converted to its proper type. Services
 * downstream never re-parse a string or wonder whether a phone number is
 * well-formed.
 *
 * All field errors are collected in one pass, so a patient filling in nine
 * fields is told everything that is wrong at once.
 */
final readonly class BookingRequest
{
    public function __construct(
        public string $patientName,
        public PhoneNumber $patientPhone,
        public ?string $patientEmail,
        public ?string $notes,
        public ?int $serviceId,
        public ?int $packageId,
        public ?int $doctorId,
        public DateTimeImmutable $date,
        public TimeSlot $timeSlot,
        public QueueTier $queueTier,
        public Locale $locale,
        public BookingSource $source,
        public ?string $ipBinary = null,
        public ?int $staffUserId = null,
    ) {
    }

    /**
     * Build from an HTTP request.
     *
     * @throws ValidationException
     */
    public static function fromRequest(
        Request $request,
        Locale $locale,
        BookingSource $source = BookingSource::WEB,
        ?int $staffUserId = null,
        bool $trustProxy = false,
    ): self {
        $errors = [];

        // --- Patient name ---
        $name = $request->input('patient_name') ?? '';

        if ($name === '') {
            $errors['patient_name'][] = 'Please enter the patient name.';
        } elseif (mb_strlen($name) < 2) {
            $errors['patient_name'][] = 'Please enter the full name.';
        } elseif (mb_strlen($name) > 160) {
            $errors['patient_name'][] = 'The name is too long.';
        }

        // --- Phone ---
        $phone    = null;
        $rawPhone = $request->input('patient_phone') ?? '';

        if ($rawPhone === '') {
            $errors['patient_phone'][] = 'Please enter a phone number so we can confirm your appointment.';
        } else {
            $phone = PhoneNumber::tryFrom($rawPhone);

            if ($phone === null) {
                $errors['patient_phone'][] = 'Please enter a valid Ethiopian phone number, for example 0911 123 456.';
            }
        }

        // --- Email (optional, but validated when supplied) ---
        $email = $request->input('patient_email');

        if ($email !== null) {
            $email = mb_strtolower($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['patient_email'][] = 'Please enter a valid email address.';
            } elseif (mb_strlen($email) > 190) {
                $errors['patient_email'][] = 'That email address is too long.';
            }
        }

        // --- Subject: service or package ---
        $serviceId = $request->nullableInt('service_id');
        $packageId = $request->nullableInt('package_id');

        if ($serviceId === null && $packageId === null) {
            $errors['service_id'][] = 'Please choose a service or a health package.';
        }

        // --- Date ---
        $date    = null;
        $rawDate = $request->input('appointment_date') ?? '';

        if ($rawDate === '') {
            $errors['appointment_date'][] = 'Please choose a date.';
        } else {
            // createFromFormat with a strict check, because DateTimeImmutable
            // would happily accept "next tuesday" or roll 2026-02-31 over
            // into March.
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
            $errorInfo = DateTimeImmutable::getLastErrors();

            if ($parsed === false || ($errorInfo !== false && ($errorInfo['warning_count'] ?? 0) > 0)) {
                $errors['appointment_date'][] = 'Please enter a valid date.';
            } else {
                $date = $parsed;
            }
        }

        // --- Time slot ---
        $slot    = null;
        $rawSlot = $request->input('time_slot') ?? '';

        if ($rawSlot === '') {
            $errors['time_slot'][] = 'Please choose a time.';
        } else {
            $slot = TimeSlot::tryFrom($rawSlot);

            if ($slot === null) {
                $errors['time_slot'][] = 'Please choose a valid time block.';
            }
        }

        // --- Optional fields ---
        $doctorId = $request->nullableInt('doctor_id');
        $tier     = QueueTier::tryFrom($request->input('queue_tier') ?? 'standard') ?? QueueTier::STANDARD;

        $notes = $request->input('patient_notes');

        if ($notes !== null && mb_strlen($notes) > 2000) {
            $errors['patient_notes'][] = 'Please keep your notes under 2000 characters.';
        }

        if ($errors !== []) {
            throw ValidationException::withErrors($errors);
        }

        // Null-safety for the analyser: every one of these is guaranteed
        // non-null by the checks above.
        assert($phone instanceof PhoneNumber);
        assert($date instanceof DateTimeImmutable);
        assert($slot instanceof TimeSlot);

        return new self(
            patientName:  $name,
            patientPhone: $phone,
            patientEmail: $email,
            notes:        $notes,
            serviceId:    $serviceId,
            packageId:    $packageId,
            doctorId:     $doctorId,
            date:         $date,
            timeSlot:     $slot,
            queueTier:    $tier,
            locale:       $locale,
            source:       $source,
            ipBinary:     $request->ipBinary($trustProxy),
            staffUserId:  $staffUserId,
        );
    }
}
