<?php

declare(strict_types=1);

namespace Aster\Domain\Entity;

use Aster\Domain\Enum\DoctorStatus;
use Aster\Domain\Enum\Locale;
use Aster\Domain\ValueObject\Money;

final readonly class Doctor
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $fullName,
        public ?string $fullNameAm,
        public string $slug,
        public string $specialty,
        public ?string $specialtyAm,
        public int $experienceYears,
        public ?string $credentials,
        public ?string $bio,
        public ?string $bioAm,
        public ?string $photoPath,
        public string $initials,
        public ?string $phone,
        /** Hard ceiling on bookings per calendar day. */
        public int $dailyCapacity,
        /** Ceiling per individual two-hour slot. */
        public int $slotCapacity,
        public Money $consultationFee,
        public DoctorStatus $status,
        public int $sortOrder,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int) $row['id'],
            userId:          isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            fullName:        (string) $row['full_name'],
            fullNameAm:      self::nullableString($row['full_name_am'] ?? null),
            slug:            (string) $row['slug'],
            specialty:       (string) $row['specialty'],
            specialtyAm:     self::nullableString($row['specialty_am'] ?? null),
            experienceYears: (int) ($row['experience_years'] ?? 0),
            credentials:     self::nullableString($row['credentials'] ?? null),
            bio:             self::nullableString($row['bio'] ?? null),
            bioAm:           self::nullableString($row['bio_am'] ?? null),
            photoPath:       self::nullableString($row['photo_path'] ?? null),
            initials:        (string) ($row['initials'] ?? ''),
            phone:           self::nullableString($row['phone'] ?? null),
            dailyCapacity:   (int) ($row['daily_capacity'] ?? 16),
            slotCapacity:    (int) ($row['slot_capacity'] ?? 4),
            consultationFee: Money::fromDatabase($row['consultation_fee'] ?? 0),
            status:          DoctorStatus::from((string) ($row['status'] ?? 'active')),
            sortOrder:       (int) ($row['sort_order'] ?? 0),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Name in the requested language, falling back to English.
     *
     * Falling back rather than showing an empty string is deliberate: a
     * half-translated roster should degrade to readable English, not to gaps.
     */
    public function name(Locale $locale): string
    {
        return $locale === Locale::AM && $this->fullNameAm !== null
            ? $this->fullNameAm
            : $this->fullName;
    }

    public function specialtyLabel(Locale $locale): string
    {
        return $locale === Locale::AM && $this->specialtyAm !== null
            ? $this->specialtyAm
            : $this->specialty;
    }

    public function biography(Locale $locale): ?string
    {
        return $locale === Locale::AM && $this->bioAm !== null ? $this->bioAm : $this->bio;
    }

    /** Derive initials when the column was left blank. */
    public function displayInitials(): string
    {
        if ($this->initials !== '') {
            return $this->initials;
        }

        $parts   = preg_split('/\s+/', trim($this->fullName)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            if (in_array(rtrim(mb_strtolower($part), '.'), ['dr', 'prof', 'mr', 'mrs', 'ms'], true)) {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($part, 0, 1));

            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($this->fullName, 0, 2));
    }

    public function isBookable(): bool
    {
        return $this->status->isBookable();
    }

    public function experienceLabel(): string
    {
        return $this->experienceYears === 1
            ? '1 year experience'
            : $this->experienceYears . ' years experience';
    }

    public function hasPhoto(): bool
    {
        return $this->photoPath !== null;
    }
}
