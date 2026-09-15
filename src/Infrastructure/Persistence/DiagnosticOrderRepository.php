<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\DiagnosticOrder;
use Aster\Domain\Repository\DiagnosticOrderRepositoryInterface;
use Aster\Infrastructure\Security\Encryptor;

final class DiagnosticOrderRepository implements DiagnosticOrderRepositoryInterface
{
    private const string SELECT_BASE = '
        SELECT o.*, u.full_name AS physician_name
        FROM diagnostic_orders o
        JOIN users u ON u.id = o.ordering_physician_id
    ';

    public function __construct(
        private readonly Database $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function findById(int $id): ?DiagnosticOrder
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE o.id = :id', ['id' => $id]);

        return $row === null ? null : DiagnosticOrder::fromRow($row);
    }

    /** @return list<DiagnosticOrder> */
    public function forEncounter(int $encounterId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE o.encounter_id = :eid ORDER BY o.created_at ASC',
            ['eid' => $encounterId],
        );

        return array_map(DiagnosticOrder::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO diagnostic_orders (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    /** @param array{status?: string, results_payload_encrypted?: ?string} $data */
    public function updateProgress(int $id, array $data): bool
    {
        $allowed = array_intersect_key($data, ['status' => true, 'results_payload_encrypted' => true]);

        if ($allowed === []) {
            return false;
        }

        $assignments = implode(', ', array_map(
            static fn (string $c): string => Database::quoteIdentifier($c) . ' = :' . $c,
            array_keys($allowed),
        ));

        $allowed['id'] = $id;

        return $this->db->execute("UPDATE diagnostic_orders SET {$assignments} WHERE id = :id", $allowed) > 0;
    }

    public function encryptResults(string $plaintext): string
    {
        return $this->encryptor->encrypt($plaintext);
    }

    public function decryptResults(DiagnosticOrder $order): ?string
    {
        return $order->resultsPayloadEncrypted !== null
            ? $this->encryptor->decrypt($order->resultsPayloadEncrypted)
            : null;
    }
}
