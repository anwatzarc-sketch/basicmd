<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Entity\DiagnosticOrder;
use MediCareMini\Domain\Enum\DiagnosticCategory;
use MediCareMini\Domain\Enum\DiagnosticStatus;
use MediCareMini\Domain\Repository\DiagnosticOrderRepositoryInterface;
use MediCareMini\Infrastructure\Security\Encryptor;

final class DiagnosticOrderRepository implements DiagnosticOrderRepositoryInterface
{
    /**
     * Every read carries the patient and encounter context, because every
     * screen that shows an order shows it beside a person - the work
     * queue cannot render "Haemoglobin, Ordered" with no name attached,
     * and the report header needs demographics. One join set used by all
     * reads beats a second, richer SELECT that could drift from this one.
     *
     * The encounters/patients joins are INNER: encounter_id is NOT NULL
     * with an FK, and encounters.patient_id likewise, so an order with no
     * patient behind it is not a row this application can produce.
     */
    private const string SELECT_BASE = '
        SELECT o.*,
               u.full_name  AS physician_name,
               t.full_name  AS resulted_by_name,
               e.patient_id AS patient_id,
               e.patient_visit_number AS visit_number,
               CONCAT_WS(\' \', p.first_name, p.last_name) AS patient_name,
               p.pid           AS patient_pid,
               p.gender        AS patient_gender,
               p.date_of_birth AS patient_dob
        FROM diagnostic_orders o
        JOIN users u       ON u.id = o.ordering_physician_id
        LEFT JOIN users t  ON t.id = o.resulted_by_id
        JOIN encounters e  ON e.id = o.encounter_id
        JOIN patients p    ON p.id = e.patient_id
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

    public function findByAccessionNumber(string $accessionNumber): ?DiagnosticOrder
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE o.accession_number = :acc',
            ['acc' => $accessionNumber],
        );

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

    /** @return list<DiagnosticOrder> */
    public function forPatient(int $patientId, int $limit = 50, ?DiagnosticCategory $category = null): array
    {
        $categoryClause = $category !== null ? ' AND o.category = :category' : '';

        $params = ['pid' => $patientId, 'limit' => $limit];

        if ($category !== null) {
            $params['category'] = $category->value;
        }

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . '
             WHERE e.patient_id = :pid' . $categoryClause . '
             ORDER BY o.created_at DESC
             LIMIT :limit',
            $params,
        );

        return array_map(DiagnosticOrder::fromRow(...), $rows);
    }

    /**
     * The laboratory work queue.
     *
     * $category is applied in SQL for the same reason forPatient()'s is:
     * a Lab Technician's "Lab only" scope must hold at the query, not as
     * a display-layer filter over a broader list.
     *
     * The search term is matched across accession number, specimen
     * barcode, patient name, PID, visit number and test name - each with
     * its own placeholder via Database::searchClause(), since a real
     * prepared statement permits a named placeholder exactly once.
     *
     * @return list<DiagnosticOrder>
     */
    public function queue(
        ?DiagnosticCategory $category = null,
        ?DiagnosticStatus $status = null,
        ?string $search = null,
        int $limit = 100,
    ): array {
        $conditions = [];
        $params     = ['limit' => $limit];

        if ($category !== null) {
            $conditions[]       = 'o.category = :category';
            $params['category'] = $category->value;
        }

        if ($status !== null) {
            $conditions[]     = 'o.status = :status';
            $params['status'] = $status->value;
        }

        $search = $search !== null ? trim($search) : '';

        if ($search !== '') {
            [$clause, $searchParams] = Database::searchClause([
                'o.accession_number',
                'o.specimen_barcode',
                'o.test_name',
                'e.patient_visit_number',
                'p.pid',
                "CONCAT_WS(' ', p.first_name, p.last_name)",
            ], $search);

            $conditions[] = $clause;
            $params      += $searchParams;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where . '
             ORDER BY FIELD(o.status, \'ORDERED\', \'IN_PROGRESS\', \'COMPLETED\', \'CANCELLED\'),
                      o.created_at DESC
             LIMIT :limit',
            $params,
        );

        return array_map(DiagnosticOrder::fromRow(...), $rows);
    }

    /**
     * How many orders sit in each status, for the queue's filter chips
     * and the sidebar badge. Counted in SQL rather than by loading the
     * queue and tallying in PHP, so the badge stays correct past the
     * queue's own LIMIT.
     *
     * @return array<string, int> status value => count
     */
    public function statusCounts(?DiagnosticCategory $category = null): array
    {
        $params = [];
        $where  = '';

        if ($category !== null) {
            $where              = ' WHERE category = :category';
            $params['category'] = $category->value;
        }

        /** @var array<string, int> $counts */
        $counts = array_map(
            static fn (mixed $value): int => (int) $value,
            $this->db->fetchPairs(
                'SELECT status, COUNT(*) FROM diagnostic_orders' . $where . ' GROUP BY status',
                $params,
            ),
        );

        return $counts;
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

    /**
     * @param array<string, mixed> $data
     */
    public function updateProgress(int $id, array $data): bool
    {
        $allowed = array_intersect_key($data, [
            'status'                    => true,
            'results_payload_encrypted' => true,
            // Specimen and authorship: progress toward a result, never
            // the order itself. See the interface's docblock.
            'specimen_type'             => true,
            'specimen_barcode'          => true,
            'collected_at'              => true,
            'clinical_location'         => true,
            'resulted_by_id'            => true,
            'resulted_at'               => true,
        ]);

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
