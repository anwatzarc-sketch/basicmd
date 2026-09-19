<?php

declare(strict_types=1);

namespace MediCareMini\Domain\Entity;

use MediCareMini\Domain\Enum\BloodGroup;
use MediCareMini\Domain\Enum\Gender;
use MediCareMini\Domain\ValueObject\PatientId;
use MediCareMini\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

/**
 * A registered patient - the Master Patient Index record (FRS 5.2).
 *
 * allergiesEncrypted carries CIPHERTEXT, never plaintext - the entity does
 * not decrypt itself, the same way Payment carries a proof file PATH and
 * never reads the file's contents. Decryption needs the application's key
 * (Encryptor, an Infrastructure class), and a Domain entity must not
 * depend on Infrastructure; PatientRepository exposes a decryptAllergies()
 * method for the one place that actually needs the plaintext.
 */
final readonly class Patient
{
    public function __construct(
        public int $id,
        public PatientId $pid,
        public ?string $nationalId,
        public string $firstName,
        public string $lastName,
        public DateTimeImmutable $dateOfBirth,
        public Gender $gender,
        public PhoneNumber $phoneNumber,
        public ?string $email,
        public ?string $address,
        public ?string $emergencyContactName,
        public ?string $emergencyContactPhone,
        public ?BloodGroup $bloodGroup,
        public ?string $allergiesEncrypted,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:                    (int) $row['id'],
            pid:                   PatientId::fromString((string) $row['pid']),
            nationalId:            self::nullableString($row['national_id'] ?? null),
            firstName:             (string) $row['first_name'],
            lastName:              (string) $row['last_name'],
            dateOfBirth:           new DateTimeImmutable((string) $row['date_of_birth']),
            gender:                Gender::from((string) $row['gender']),
            phoneNumber:           PhoneNumber::fromString((string) $row['phone_number']),
            email:                 self::nullableString($row['email'] ?? null),
            address:               self::nullableString($row['address'] ?? null),
            emergencyContactName:  self::nullableString($row['emergency_contact_name'] ?? null),
            emergencyContactPhone: self::nullableString($row['emergency_contact_phone'] ?? null),
            bloodGroup:            isset($row['blood_group']) && $row['blood_group'] !== null
                                        ? BloodGroup::from((string) $row['blood_group'])
                                        : null,
            allergiesEncrypted:    self::nullableString($row['allergies_encrypted'] ?? null),
            createdAt:             self::toDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value . ' UTC');
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function age(?DateTimeImmutable $asOf = null): int
    {
        $asOf = $asOf ?? new DateTimeImmutable();

        return $this->dateOfBirth->diff($asOf)->y;
    }

    public function hasAllergies(): bool
    {
        return $this->allergiesEncrypted !== null;
    }

    public function initials(): string
    {
        $first = mb_substr($this->firstName, 0, 1);
        $last  = mb_substr($this->lastName, 0, 1);

        return mb_strtoupper($first . $last);
    }
}
