<?php

declare(strict_types=1);

namespace Aster\Tests\Unit\Domain\DTO;

use Aster\Domain\DTO\LabReport;
use Aster\Domain\DTO\LabResultLine;
use Aster\Domain\Enum\LabResultFlag;
use PHPUnit\Framework\TestCase;

/**
 * LabReport is what goes through the encryptor, so its encode/decode pair
 * has to round-trip exactly - and, just as importantly, has to keep
 * reading the free-text payloads migration 004 shipped.
 */
final class LabReportTest extends TestCase
{
    private static function report(): LabReport
    {
        return new LabReport(
            title:      'Haematology - Complete Blood Count (CBC)',
            panelCode:  'CBC',
            lines:      [
                new LabResultLine('Haemoglobin (Hgb)', '10.2', 'g/dL', 12.0, 16.0, null, 'Spectrophotometry'),
                new LabResultLine('Platelet Count', '260', 'x10^3/uL', 150.0, 450.0, null, 'Impedance'),
                new LabResultLine('Nitrite', 'Negative', '', null, null, 'Negative', 'Diazo'),
                new LabResultLine('Free Hb', '', 'g/dL', 12.0, 16.0, null, 'Calculated'),
            ],
            impression: 'Microcytic picture; correlate with iron studies.',
        );
    }

    public function test_round_trips_through_encode_and_decode(): void
    {
        $decoded = LabReport::decode(self::report()->encode());

        self::assertSame('Haematology - Complete Blood Count (CBC)', $decoded->title);
        self::assertSame('CBC', $decoded->panelCode);
        self::assertSame('Microcytic picture; correlate with iron studies.', $decoded->impression);
        self::assertCount(4, $decoded->lines);
        self::assertSame('Haemoglobin (Hgb)', $decoded->lines[0]->parameterName);
        self::assertSame(12.0, $decoded->lines[0]->referenceMin);
        self::assertSame('Negative', $decoded->lines[2]->referenceText);
        self::assertNull($decoded->lines[2]->referenceMin);
    }

    public function test_a_line_with_no_value_is_not_reported(): void
    {
        $report = self::report();

        self::assertCount(4, $report->lines);
        self::assertCount(3, $report->reportedLines());
    }

    public function test_counts_only_reported_lines_as_abnormal(): void
    {
        $report = self::report();

        // Hgb is LOW; platelets and nitrite are normal; the blank Free Hb
        // line is not a finding at all even though its value would be.
        self::assertSame(1, $report->abnormalCount());
        self::assertSame(0, $report->criticalCount());
        self::assertSame(LabResultFlag::LOW, $report->worstFlag());
    }

    public function test_a_critical_value_wins_the_worst_flag(): void
    {
        $report = self::report()->withLines([
            new LabResultLine('Hgb', '10.2', 'g/dL', 12.0, 16.0, null, ''),
            new LabResultLine('Free Hb', '4.0', 'g/dL', 12.0, 16.0, null, ''),
        ]);

        self::assertSame(2, $report->abnormalCount());
        self::assertSame(1, $report->criticalCount());
        self::assertSame(LabResultFlag::CRITICAL_LOW, $report->worstFlag());
    }

    public function test_a_legacy_free_text_payload_becomes_the_impression(): void
    {
        // Every result recorded before migration 012 is plain text in this
        // column. It must still open, and must not be mistaken for a
        // structured report.
        $report = LabReport::decode('Haemolysed sample, repeat requested.', 'Serum electrolytes');

        self::assertFalse($report->isStructured());
        self::assertSame('Serum electrolytes', $report->title);
        self::assertSame('Haemolysed sample, repeat requested.', $report->impression);
        self::assertSame([], $report->lines);
        self::assertSame(0, $report->abnormalCount());
    }

    public function test_a_payload_from_an_unknown_version_is_kept_as_text(): void
    {
        $future = (string) json_encode(['v' => 99, 'lines' => [['name' => 'X', 'value' => '1']]]);
        $report = LabReport::decode($future, 'Fallback');

        self::assertFalse($report->isStructured());
        self::assertSame($future, $report->impression);
    }

    public function test_reference_display_reads_the_same_for_every_shape(): void
    {
        $both  = new LabResultLine('a', '1', '', 12.0, 16.0, null, '');
        $upper = new LabResultLine('b', '1', '', null, 34.0, null, '');
        $lower = new LabResultLine('c', '1', '', 60.0, null, null, '');
        $text  = new LabResultLine('d', 'Negative', '', null, null, 'Negative', '');
        $none  = new LabResultLine('e', 'x', '', null, null, null, '');

        self::assertSame('12 - 16', $both->referenceDisplay());
        self::assertSame('Up to 34', $upper->referenceDisplay());
        self::assertSame('Above 60', $lower->referenceDisplay());
        self::assertSame('Negative', $text->referenceDisplay());
        self::assertSame('-', $none->referenceDisplay());
    }

    public function test_decimal_tails_are_trimmed_for_display(): void
    {
        $line = new LabResultLine('Creatinine', '0.95', 'mg/dL', 0.6, 1.2, null, '');

        self::assertSame('0.6 - 1.2', $line->referenceDisplay());
    }
}
