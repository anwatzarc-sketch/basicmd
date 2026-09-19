<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\PatientAccount;
use MediCareMini\Domain\Repository\PatientAccountRepositoryInterface;
use DateTimeImmutable;

final class PatientAccountRepository implements PatientAccountRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?PatientAccount
    {
        $row = $this->db->fetchOne('SELECT * FROM patient_accounts WHERE id = :id', ['id' => $id]);

        return $row === null ? null : PatientAccount::fromRow($row);
    }

    public function findByPatientId(int $patientId): ?PatientAccount
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM patient_accounts WHERE patient_id = :pid',
            ['pid' => $patientId],
        );

        return $row === null ? null : PatientAccount::fromRow($row);
    }

    /** @return array<string, mixed>|null */
    public function findCredentialsByPid(string $pid): ?array
    {
        return $this->db->fetchOne(
            'SELECT a.id, a.patient_id, a.password_hash, a.is_active
             FROM patient_accounts a
             JOIN patients p ON p.id = a.patient_id
             WHERE p.pid = :pid AND p.deleted_at IS NULL',
            ['pid' => $pid],
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO patient_accounts (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    public function updatePasswordHash(int $accountId, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE patient_accounts SET password_hash = :hash WHERE id = :id',
            ['hash' => $passwordHash, 'id' => $accountId],
        );
    }

    public function recordLogin(int $accountId): void
    {
        $this->db->execute(
            'UPDATE patient_accounts SET last_login_at = :now WHERE id = :id',
            ['now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'), 'id' => $accountId],
        );
    }
}
