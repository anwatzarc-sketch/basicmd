<?php

declare(strict_types=1);

namespace Aster\Domain\DTO;

use Aster\Domain\Enum\LabResultFlag;

/**
 * The decoded contents of diagnostic_orders.results_payload_encrypted for
 * a Lab order: the report title, the measured lines, and the
 * pathologist's written impression.
 *
 * This object is the PLAINTEXT side of that column. It exists only
 * between LabReportService decrypting and a template rendering; nothing
 * persists it directly, exactly as ClinicalNote's plaintext never lives
 * on the entity.
 *
 * LEGACY PAYLOADS - migration 004 shipped this column as free text, and
 * orders resulted before migration 012 still hold exactly that. decode()
 * therefore accepts either shape: anything that is not a version-tagged
 * JSON object is taken as a written impression with no measured lines,
 * which is what it literally is. That is why isStructured() exists and
 * why the report template prints the impression whether or not there is
 * a table above it.
 */
final readonly class LabReport
{
    /** Payload schema version, so a future shape change is detectable. */
    public const int VERSION = 1;

    /** @param list<LabResultLine> $lines */
    public function __construct(
        public string $title,
        public ?string $panelCode,
        public array $lines,
        public string $impression,
    ) {
    }

    /**
     * Read a decrypted payload.
     *
     * Never throws: a payload that cannot be parsed is still somebody's
     * result, and losing it behind an exception on the report screen
     * would be worse than showing it as unstructured text.
     */
    public static function decode(string $plaintext, string $fallbackTitle = 'Laboratory Report'): self
    {
        $decoded = json_validate($plaintext) ? json_decode($plaintext, true) : null;

        if (!is_array($decoded) || ($decoded['v'] ?? null) !== self::VERSION) {
            return new self($fallbackTitle, null, [], trim($plaintext));
        }

        $lines = [];

        foreach (is_array($decoded['lines'] ?? null) ? $decoded['lines'] : [] as $line) {
            if (is_array($line)) {
                $lines[] = LabResultLine::fromArray($line);
            }
        }

        $title = trim((string) ($decoded['title'] ?? ''));

        return new self(
            title:      $title !== '' ? $title : $fallbackTitle,
            panelCode:  isset($decoded['panel']) && trim((string) $decoded['panel']) !== ''
                            ? trim((string) $decoded['panel'])
                            : null,
            lines:      $lines,
            impression: trim((string) ($decoded['impression'] ?? '')),
        );
    }

    /** The plaintext to hand the encryptor. */
    public function encode(): string
    {
        return (string) json_encode([
            'v'          => self::VERSION,
            'title'      => $this->title,
            'panel'      => $this->panelCode,
            'impression' => $this->impression,
            'lines'      => array_map(static fn (LabResultLine $line): array => $line->toArray(), $this->lines),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Was this written by the structured entry screen, or is it legacy free text? */
    public function isStructured(): bool
    {
        return $this->lines !== [];
    }

    /** @return list<LabResultLine> only the lines a value was actually entered for */
    public function reportedLines(): array
    {
        return array_values(array_filter($this->lines, static fn (LabResultLine $line): bool => $line->isReported()));
    }

    /** How many reported lines fall outside their reference interval. */
    public function abnormalCount(): int
    {
        return count(array_filter(
            $this->reportedLines(),
            static fn (LabResultLine $line): bool => $line->flag()->isAbnormal(),
        ));
    }

    /** How many reported lines are grossly outside it. */
    public function criticalCount(): int
    {
        return count(array_filter(
            $this->reportedLines(),
            static fn (LabResultLine $line): bool => $line->flag()->isCritical(),
        ));
    }

    /** @return list<LabResultLine> the abnormal lines, for the pre-release summary */
    public function abnormalLines(): array
    {
        return array_values(array_filter(
            $this->reportedLines(),
            static fn (LabResultLine $line): bool => $line->flag()->isAbnormal(),
        ));
    }

    /** The worst flag on the report, for the queue's at-a-glance column. */
    public function worstFlag(): LabResultFlag
    {
        $worst = LabResultFlag::NORMAL;

        foreach ($this->reportedLines() as $line) {
            $flag = $line->flag();

            if ($flag->isCritical()) {
                return $flag;
            }

            if ($flag->isAbnormal()) {
                $worst = $flag;
            }
        }

        return $worst;
    }

    /** @param list<LabResultLine> $lines */
    public function withLines(array $lines): self
    {
        return new self($this->title, $this->panelCode, $lines, $this->impression);
    }
}
