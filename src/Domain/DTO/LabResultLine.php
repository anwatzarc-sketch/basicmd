<?php

declare(strict_types=1);

namespace Aster\Domain\DTO;

use Aster\Domain\Entity\LabPanelParameter;
use Aster\Domain\Enum\LabResultFlag;

/**
 * One measured parameter on a report: what was asked for, what came back,
 * and the interval it was judged against.
 *
 * The reference bounds are COPIED here from the catalogue rather than
 * looked up at render time. A report is a signed statement about what was
 * normal on the day it was released; re-reading today's catalogue when
 * reprinting a two-year-old report would silently restate it against
 * bounds nobody applied at the time.
 *
 * The flag is NOT stored - flag() derives it through LabResultFlag, so a
 * correction to the flag engine takes effect everywhere at once and no
 * payload can carry a flag that disagrees with its own value.
 */
final readonly class LabResultLine
{
    public function __construct(
        public string $parameterName,
        public string $value,
        public string $unit,
        public ?float $referenceMin,
        public ?float $referenceMax,
        public ?string $referenceText,
        public string $methodology,
    ) {
    }

    /** A blank line for this catalogue parameter, ready to be keyed into. */
    public static function fromParameter(LabPanelParameter $parameter, string $value = ''): self
    {
        return new self(
            parameterName: $parameter->parameterName,
            value:         $value,
            unit:          $parameter->unit,
            referenceMin:  $parameter->referenceMin,
            referenceMax:  $parameter->referenceMax,
            referenceText: $parameter->referenceText,
            methodology:   $parameter->methodology,
        );
    }

    /** @param array<string, mixed> $data one entry of the stored payload */
    public static function fromArray(array $data): self
    {
        return new self(
            parameterName: trim((string) ($data['name'] ?? '')),
            value:         trim((string) ($data['value'] ?? '')),
            unit:          trim((string) ($data['unit'] ?? '')),
            referenceMin:  isset($data['min']) && is_numeric($data['min']) ? (float) $data['min'] : null,
            referenceMax:  isset($data['max']) && is_numeric($data['max']) ? (float) $data['max'] : null,
            referenceText: isset($data['text']) && trim((string) $data['text']) !== ''
                               ? trim((string) $data['text'])
                               : null,
            methodology:   trim((string) ($data['method'] ?? '')),
        );
    }

    /**
     * Short keys, because this array is JSON-encoded and then encrypted
     * per order - the payload is stored, not read by humans.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'   => $this->parameterName,
            'value'  => $this->value,
            'unit'   => $this->unit,
            'min'    => $this->referenceMin,
            'max'    => $this->referenceMax,
            'text'   => $this->referenceText,
            'method' => $this->methodology,
        ];
    }

    public function flag(): LabResultFlag
    {
        return LabResultFlag::evaluate($this->value, $this->referenceMin, $this->referenceMax, $this->referenceText);
    }

    public function isReported(): bool
    {
        return trim($this->value) !== '';
    }

    public function isQuantitative(): bool
    {
        return $this->referenceMin !== null || $this->referenceMax !== null;
    }

    /** The interval as printed. Mirrors LabPanelParameter::referenceDisplay(). */
    public function referenceDisplay(): string
    {
        if (!$this->isQuantitative()) {
            return $this->referenceText ?? '-';
        }

        $min = $this->referenceMin;
        $max = $this->referenceMax;

        if ($min !== null && $max !== null) {
            return LabPanelParameter::number($min) . ' - ' . LabPanelParameter::number($max);
        }

        return $min !== null
            ? 'Above ' . LabPanelParameter::number($min)
            : 'Up to ' . LabPanelParameter::number($max ?? 0.0);
    }
}
