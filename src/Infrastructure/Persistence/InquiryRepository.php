<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\ContactInquiry;
use Aster\Domain\Enum\InquiryStatus;

final class InquiryRepository
{
    private const string SELECT_BASE = '
        SELECT i.*, u.full_name AS handler_name
        FROM contact_inquiries i
        LEFT JOIN users u ON u.id = i.handled_by
    ';

    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO contact_inquiries (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns))
            . ') VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    public function findById(int $id): ?ContactInquiry
    {
        $row = $this->db->fetchOne(
            self::SELECT_BASE . ' WHERE i.id = :id AND i.deleted_at IS NULL',
            ['id' => $id],
        );

        return $row === null ? null : ContactInquiry::fromRow($row);
    }

    /**
     * @param array{status?:string, search?:string} $filters
     * @return list<ContactInquiry>
     */
    public function search(array $filters, int $limit = 25, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $params['limit']  = $limit;
        $params['offset'] = $offset;

        $rows = $this->db->fetchAll(
            self::SELECT_BASE . $where
            // Unread first, then oldest: a message waiting three days must
            // not be buried under one that arrived a minute ago.
            . " ORDER BY FIELD(i.status, 'unread', 'in_progress', 'responded', 'archived', 'spam'),
                        i.created_at ASC
               LIMIT :limit OFFSET :offset",
            $params,
        );

        return array_map(ContactInquiry::fromRow(...), $rows);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildFilters($filters);

        return $this->db->fetchInt('SELECT COUNT(*) FROM contact_inquiries i' . $where, $params);
    }

    /** @return array{string, array<string, mixed>} */
    private function buildFilters(array $filters): array
    {
        $where  = ['i.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'i.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            [$clause, $searchParams] = Database::searchClause(
                ['i.name', 'i.phone', 'i.email', 'i.message'],
                (string) $filters['search'],
            );

            $where[] = $clause;
            $params  = [...$params, ...$searchParams];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    public function countUnread(): int
    {
        return $this->db->fetchInt(
            "SELECT COUNT(*) FROM contact_inquiries
             WHERE status IN ('unread', 'in_progress') AND deleted_at IS NULL"
        );
    }

    /** @return list<ContactInquiry> */
    public function recent(int $limit = 5): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT :limit',
            ['limit' => $limit],
        );

        return array_map(ContactInquiry::fromRow(...), $rows);
    }

    public function updateStatus(int $id, InquiryStatus $status, int $handlerId, ?string $notes = null): bool
    {
        $sql = 'UPDATE contact_inquiries
                SET status = :status, handled_by = :handler';

        $params = ['status' => $status->value, 'handler' => $handlerId, 'id' => $id];

        if ($status === InquiryStatus::RESPONDED) {
            $sql .= ', responded_at = UTC_TIMESTAMP()';
        }

        if ($notes !== null) {
            $sql            .= ', notes = :notes';
            $params['notes'] = $notes;
        }

        return $this->db->execute($sql . ' WHERE id = :id AND deleted_at IS NULL', $params) > 0;
    }

    public function softDelete(int $id): bool
    {
        return $this->db->execute(
            'UPDATE contact_inquiries SET deleted_at = UTC_TIMESTAMP() WHERE id = :id',
            ['id' => $id],
        ) > 0;
    }

    /** @return array<string,mixed>|null */
    public function rawRow(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM contact_inquiries WHERE id = :id', ['id' => $id]);
    }
}
