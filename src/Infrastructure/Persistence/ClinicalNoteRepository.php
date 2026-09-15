<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Entity\ClinicalNote;
use Aster\Domain\Repository\ClinicalNoteRepositoryInterface;
use Aster\Infrastructure\Security\Encryptor;

final class ClinicalNoteRepository implements ClinicalNoteRepositoryInterface
{
    private const string SELECT_BASE = '
        SELECT n.*, u.full_name AS author_name
        FROM clinical_notes n
        JOIN users u ON u.id = n.author_id
    ';

    public function __construct(
        private readonly Database $db,
        private readonly Encryptor $encryptor,
    ) {
    }

    public function findById(int $id): ?ClinicalNote
    {
        $row = $this->db->fetchOne(self::SELECT_BASE . ' WHERE n.id = :id', ['id' => $id]);

        return $row === null ? null : ClinicalNote::fromRow($row);
    }

    /** @return list<ClinicalNote> */
    public function forEncounter(int $encounterId): array
    {
        $rows = $this->db->fetchAll(
            self::SELECT_BASE . ' WHERE n.encounter_id = :eid ORDER BY n.created_at ASC',
            ['eid' => $encounterId],
        );

        return array_map(ClinicalNote::fromRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $data 'vitals' may be a PHP array (encoded
     *   to JSON here) or omitted entirely; every other key binds as-is.
     */
    public function create(array $data): int
    {
        if (array_key_exists('vitals', $data)) {
            $vitals = $data['vitals'];
            unset($data['vitals']);
            $data['vitals_json'] = $vitals !== null ? json_encode($vitals, JSON_THROW_ON_ERROR) : null;
        }

        $columns = array_keys($data);

        $this->db->execute(
            'INSERT INTO clinical_notes (' . implode(', ', array_map(Database::quoteIdentifier(...), $columns)) . ')
             VALUES (' . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')',
            $data,
        );

        return $this->db->lastInsertId();
    }

    public function encryptContent(string $plaintext): string
    {
        return $this->encryptor->encrypt($plaintext);
    }

    public function decryptContent(ClinicalNote $note): string
    {
        return $this->encryptor->decrypt($note->contentEncrypted);
    }
}
