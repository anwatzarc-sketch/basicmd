<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Feature\Persistence;

use MediCareMini\Domain\Entity\ClinicalNote;
use MediCareMini\Domain\Entity\DiagnosticOrder;
use MediCareMini\Domain\Enum\ClinicalNoteType;
use MediCareMini\Domain\Enum\DiagnosticCategory;
use MediCareMini\Domain\Enum\DiagnosticStatus;
use MediCareMini\Domain\Enum\VisitType;
use MediCareMini\Domain\Repository\ClinicalNoteRepositoryInterface;
use MediCareMini\Domain\Repository\DiagnosticOrderRepositoryInterface;
use MediCareMini\Domain\Repository\PrescriptionRepositoryInterface;
use MediCareMini\Domain\Services\EncounterService;
use MediCareMini\Infrastructure\Persistence\AuditLogger;
use MediCareMini\Tests\Support\DatabaseTestCase;
use PDOException;

/**
 * FRS AC-CLN-01 through AC-CLN-07, against the real database.
 */
final class ClinicalRecordsTest extends DatabaseTestCase
{
    private ClinicalNoteRepositoryInterface $notes;
    private DiagnosticOrderRepositoryInterface $orders;
    private PrescriptionRepositoryInterface $prescriptions;

    /** dawit@medicaremini.radiants.net.et, seeded physician. */
    private const int PHYSICIAN_ID = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notes         = $this->container->get(ClinicalNoteRepositoryInterface::class);
        $this->orders         = $this->container->get(DiagnosticOrderRepositoryInterface::class);
        $this->prescriptions  = $this->container->get(PrescriptionRepositoryInterface::class);
    }

    private function makeEncounter(): int
    {
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Clinical', 'TestPatient', '1992-02-02', 'other', :phone, UTC_TIMESTAMP())",
            ['pid' => 'PID-7000-' . random_int(10000, 99999), 'phone' => '+2519' . random_int(10000000, 99999999)],
        );
        $patientId = $this->db->lastInsertId();

        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);

        return $encounterService->startEncounter($patientId, VisitType::OPD, null)->id;
    }

    // -----------------------------------------------------------------
    //  clinical_notes - AC-CLN-01, AC-CLN-02, AC-CLN-07
    // -----------------------------------------------------------------

    public function test_a_note_must_reference_a_valid_encounter(): void
    {
        $this->expectException(PDOException::class);
        $this->notes->create([
            'encounter_id'      => 999999999,
            'author_id'          => self::PHYSICIAN_ID,
            'note_type'          => ClinicalNoteType::TRIAGE->value,
            'content_encrypted'  => $this->notes->encryptContent('test'),
        ]);
    }

    public function test_a_note_must_reference_an_author(): void
    {
        $encounterId = $this->makeEncounter();

        $this->expectException(PDOException::class);
        $this->notes->create([
            'encounter_id'      => $encounterId,
            'author_id'          => 999999999,
            'note_type'          => ClinicalNoteType::TRIAGE->value,
            'content_encrypted'  => $this->notes->encryptContent('test'),
        ]);
    }

    public function test_note_content_is_never_stored_as_plaintext(): void
    {
        $encounterId = $this->makeEncounter();
        $plaintext   = 'Patient reports severe headache since this morning.';

        $noteId = $this->notes->create([
            'encounter_id'      => $encounterId,
            'author_id'          => self::PHYSICIAN_ID,
            'note_type'          => ClinicalNoteType::CHIEF_COMPLAINT->value,
            'content_encrypted'  => $this->notes->encryptContent($plaintext),
        ]);

        $storedRaw = $this->db->fetchValue('SELECT content_encrypted FROM clinical_notes WHERE id = :id', ['id' => $noteId]);
        self::assertStringNotContainsString($plaintext, (string) $storedRaw, 'AC-CLN-07: content must never be plaintext in the database');

        $note = $this->notes->findById($noteId);
        self::assertSame($plaintext, $this->notes->decryptContent($note));
    }

    public function test_vitals_json_round_trips_through_a_note(): void
    {
        $encounterId = $this->makeEncounter();
        $vitals      = ['bp_systolic' => 120, 'bp_diastolic' => 80, 'hr' => 72, 'temp_c' => 36.7, 'spo2' => 98, 'weight_kg' => 70];

        $noteId = $this->notes->create([
            'encounter_id'      => $encounterId,
            'author_id'          => self::PHYSICIAN_ID,
            'note_type'          => ClinicalNoteType::VITALS->value,
            'vitals'             => $vitals,
            'content_encrypted'  => $this->notes->encryptContent('Vitals recorded.'),
        ]);

        $note = $this->notes->findById($noteId);
        self::assertSame($vitals, $note->vitals);
    }

    public function test_a_note_with_an_invalid_type_is_rejected(): void
    {
        $encounterId = $this->makeEncounter();

        $this->expectException(PDOException::class);
        $this->db->execute(
            "INSERT INTO clinical_notes (encounter_id, author_id, note_type, content_encrypted)
             VALUES (:eid, :aid, 'not_a_real_type', :content)",
            ['eid' => $encounterId, 'aid' => self::PHYSICIAN_ID, 'content' => $this->notes->encryptContent('x')],
        );
    }

    public function test_notes_for_an_encounter_are_returned_in_chronological_order(): void
    {
        $encounterId = $this->makeEncounter();

        $first = $this->notes->create([
            'encounter_id' => $encounterId, 'author_id' => self::PHYSICIAN_ID,
            'note_type' => ClinicalNoteType::TRIAGE->value, 'content_encrypted' => $this->notes->encryptContent('First'),
        ]);
        $second = $this->notes->create([
            'encounter_id' => $encounterId, 'author_id' => self::PHYSICIAN_ID,
            'note_type' => ClinicalNoteType::PROGRESS_NOTE->value, 'content_encrypted' => $this->notes->encryptContent('Second'),
        ]);

        $all = $this->notes->forEncounter($encounterId);

        self::assertCount(2, $all);
        self::assertSame($first, $all[0]->id);
        self::assertSame($second, $all[1]->id);
    }

    // -----------------------------------------------------------------
    //  diagnostic_orders - AC-CLN-03, AC-CLN-04
    // -----------------------------------------------------------------

    public function test_an_order_must_reference_encounter_and_ordering_physician(): void
    {
        $encounterId = $this->makeEncounter();

        $orderId = $this->orders->create([
            'encounter_id'           => $encounterId,
            'ordering_physician_id'   => self::PHYSICIAN_ID,
            'category'               => DiagnosticCategory::LAB->value,
            'test_code'               => 'CBC',
            'test_name'               => 'Complete Blood Count',
            'icd_code'                => 'R50.9',
        ]);

        $order = $this->orders->findById($orderId);
        self::assertSame($encounterId, $order->encounterId);
        self::assertSame(self::PHYSICIAN_ID, $order->orderingPhysicianId);
        self::assertSame(DiagnosticStatus::ORDERED, $order->status);
    }

    public function test_an_order_with_an_invalid_category_is_rejected(): void
    {
        $encounterId = $this->makeEncounter();

        $this->expectException(PDOException::class);
        $this->db->execute(
            "INSERT INTO diagnostic_orders (encounter_id, ordering_physician_id, category, test_code, test_name, icd_code)
             VALUES (:eid, :pid, 'NotACategory', 'X', 'X', 'X')",
            ['eid' => $encounterId, 'pid' => self::PHYSICIAN_ID],
        );
    }

    public function test_an_order_without_an_icd_code_is_rejected(): void
    {
        $encounterId = $this->makeEncounter();

        $this->expectException(PDOException::class);
        $this->db->execute(
            "INSERT INTO diagnostic_orders (encounter_id, ordering_physician_id, category, test_code, test_name)
             VALUES (:eid, :pid, 'Lab', 'CBC', 'Complete Blood Count')",
            ['eid' => $encounterId, 'pid' => self::PHYSICIAN_ID],
        );
    }

    public function test_results_are_never_stored_as_plaintext_and_round_trip(): void
    {
        $encounterId = $this->makeEncounter();
        $plaintext   = 'Haemoglobin 13.2 g/dL, WBC 6.1 x10^9/L, all within normal range.';

        $orderId = $this->orders->create([
            'encounter_id' => $encounterId, 'ordering_physician_id' => self::PHYSICIAN_ID,
            'category' => DiagnosticCategory::LAB->value, 'test_code' => 'CBC',
            'test_name' => 'Complete Blood Count', 'icd_code' => 'R50.9',
        ]);

        $this->orders->updateProgress($orderId, [
            'status'                     => DiagnosticStatus::COMPLETED->value,
            'results_payload_encrypted'  => $this->orders->encryptResults($plaintext),
        ]);

        $storedRaw = $this->db->fetchValue(
            'SELECT results_payload_encrypted FROM diagnostic_orders WHERE id = :id',
            ['id' => $orderId],
        );
        self::assertStringNotContainsString($plaintext, (string) $storedRaw);

        $order = $this->orders->findById($orderId);
        self::assertSame(DiagnosticStatus::COMPLETED, $order->status);
        self::assertSame($plaintext, $this->orders->decryptResults($order));
    }

    public function test_update_progress_never_touches_the_original_order_columns(): void
    {
        $encounterId = $this->makeEncounter();

        $orderId = $this->orders->create([
            'encounter_id' => $encounterId, 'ordering_physician_id' => self::PHYSICIAN_ID,
            'category' => DiagnosticCategory::IMAGING->value, 'test_code' => 'XR-CHEST',
            'test_name' => 'Chest X-Ray', 'icd_code' => 'J18.9',
        ]);

        $this->orders->updateProgress($orderId, ['status' => DiagnosticStatus::IN_PROGRESS->value]);

        $order = $this->orders->findById($orderId);
        self::assertSame('XR-CHEST', $order->testCode, 'updateProgress must never alter the original order details');
        self::assertSame('Chest X-Ray', $order->testName);
        self::assertSame(DiagnosticStatus::IN_PROGRESS, $order->status);
    }

    // -----------------------------------------------------------------
    //  e_prescriptions - AC-CLN-05, AC-CLN-06
    // -----------------------------------------------------------------

    public function test_a_prescription_must_reference_encounter_and_prescriber(): void
    {
        $encounterId = $this->makeEncounter();

        $id = $this->prescriptions->create([
            'encounter_id'     => $encounterId,
            'prescriber_id'     => self::PHYSICIAN_ID,
            'icd_code'          => 'J45.909',
            'medication_name'   => 'Salbutamol Inhaler',
            'dosage'            => '100mcg',
            'frequency'         => 'as needed',
            'duration_days'     => 30,
        ]);

        $rx = $this->prescriptions->findById($id);
        self::assertSame($encounterId, $rx->encounterId);
        self::assertSame(self::PHYSICIAN_ID, $rx->prescriberId);
        self::assertFalse($rx->isDispensed);
    }

    public function test_a_prescription_with_a_zero_duration_is_rejected(): void
    {
        $encounterId = $this->makeEncounter();

        $this->expectException(PDOException::class);
        $this->prescriptions->create([
            'encounter_id' => $encounterId, 'prescriber_id' => self::PHYSICIAN_ID,
            'icd_code' => 'J45.909', 'medication_name' => 'X', 'dosage' => 'X', 'frequency' => 'X',
            'duration_days' => 0,
        ]);
    }

    public function test_mark_dispensed_flips_only_that_field(): void
    {
        $encounterId = $this->makeEncounter();

        $id = $this->prescriptions->create([
            'encounter_id' => $encounterId, 'prescriber_id' => self::PHYSICIAN_ID,
            'icd_code' => 'J45.909', 'medication_name' => 'Amoxicillin', 'dosage' => '500mg',
            'frequency' => 'three times daily', 'duration_days' => 7,
        ]);

        $this->prescriptions->markDispensed($id);

        $rx = $this->prescriptions->findById($id);
        self::assertTrue($rx->isDispensed);
        self::assertSame('Amoxicillin', $rx->medicationName);
        self::assertSame(7, $rx->durationDays);
    }

    // -----------------------------------------------------------------
    //  Audit redaction - clinical content must never appear in an audit
    //  diff, encrypted or not (the field name alone is enough to show).
    // -----------------------------------------------------------------

    public function test_clinical_content_is_redacted_from_audit_diffs(): void
    {
        /** @var AuditLogger $audit */
        $audit = $this->container->get(AuditLogger::class);

        $before = ['content_encrypted' => 'aes256gcm.v1.oldciphertext=='];
        $after  = ['content_encrypted' => 'aes256gcm.v1.newciphertext=='];

        $audit->recordDiff(AuditLogger::CONTENT_UPDATED, 'clinical_note', 999999, $before, $after, 'test');

        $row = $this->db->fetchOne(
            "SELECT changes_json FROM audit_logs WHERE target_type = 'clinical_note' AND target_id = 999999 ORDER BY id DESC LIMIT 1",
        );

        self::assertNotNull($row);
        self::assertStringNotContainsString('ciphertext', (string) $row['changes_json']);
        self::assertStringContainsString('[redacted]', (string) $row['changes_json']);
    }
}
