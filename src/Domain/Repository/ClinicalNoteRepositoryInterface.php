<?php

declare(strict_types=1);

namespace Aster\Domain\Repository;

use Aster\Domain\Entity\ClinicalNote;

/**
 * Clinical note persistence (FRS 6.1).
 *
 * Deliberately INSERT-ONLY - there is no update() here, unlike
 * EncounterRepositoryInterface or WardLocationRepositoryInterface. A
 * clinical note, once written, stands as the record of what was observed
 * at that time; a correction is a NEW note (an addendum), never an edit
 * to an old one. This is what makes FRS 6.1's rule 6 ("notes must not be
 * assigned to a different encounter after creation") true by
 * construction rather than by a check someone has to remember to run -
 * encounter_id cannot change if nothing about the row can.
 */
interface ClinicalNoteRepositoryInterface
{
    public function findById(int $id): ?ClinicalNote;

    /** @return list<ClinicalNote> */
    public function forEncounter(int $encounterId): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): int;

    /** Encrypt plaintext note content for storage - see Encryptor's docblock. */
    public function encryptContent(string $plaintext): string;

    /** Decrypt a note's content. The one place its plaintext is read back. */
    public function decryptContent(ClinicalNote $note): string;
}
