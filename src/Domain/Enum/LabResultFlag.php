<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * How one result value sits against its reference interval.
 *
 * This is the only place a value is judged. Result entry, the printed
 * report and the queue's abnormal counter all call evaluate() rather than
 * re-deriving "is this high?" in three places - which is how two screens
 * end up disagreeing about the same number.
 *
 * The critical band is a fixed 25% beyond the interval, matching the
 * clinical convention of "grossly outside range" rather than a per-analyte
 * critical table. A real critical-value policy is analyte-specific (a
 * potassium of 6.5 is an emergency; a cholesterol 25% high is not), so
 * this deliberately labels magnitude, not urgency, and the pathologist
 * comment box is where urgency is actually stated. If per-analyte critical
 * limits are ever added they belong on lab_panel_parameters, beside the
 * reference bounds, not here.
 */
enum LabResultFlag: string
{
    case NORMAL        = 'NORMAL';
    case LOW           = 'LOW';
    case HIGH          = 'HIGH';
    case CRITICAL_LOW  = 'CRITICAL_LOW';
    case CRITICAL_HIGH = 'CRITICAL_HIGH';
    case ABNORMAL      = 'ABNORMAL';

    /** Fraction beyond a bound at which LOW/HIGH becomes CRITICAL_*. */
    private const float CRITICAL_DEVIATION = 0.25;

    public function label(): string
    {
        return match ($this) {
            self::NORMAL        => 'Normal',
            self::LOW           => 'Low',
            self::HIGH          => 'High',
            self::CRITICAL_LOW  => 'Critical low',
            self::CRITICAL_HIGH => 'Critical high',
            self::ABNORMAL      => 'Abnormal',
        };
    }

    /** The short form printed in the report's Flag column. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::NORMAL        => 'Normal',
            self::LOW           => 'L',
            self::HIGH          => 'H',
            self::CRITICAL_LOW  => 'LL',
            self::CRITICAL_HIGH => 'HH',
            self::ABNORMAL      => 'Abn',
        };
    }

    /**
     * Component class for the status pill, in the same
     * chip--built/partial/absent family EncounterStatus already emits -
     * one visual language for "a coloured status pill" across the app.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::NORMAL                         => 'chip--built',
            self::LOW, self::HIGH                => 'chip--partial',
            self::CRITICAL_LOW, self::CRITICAL_HIGH, self::ABNORMAL => 'chip--absent',
        };
    }

    public function isAbnormal(): bool
    {
        return $this !== self::NORMAL;
    }

    public function isCritical(): bool
    {
        return $this === self::CRITICAL_LOW || $this === self::CRITICAL_HIGH;
    }

    /**
     * Judge one entered value.
     *
     * $value is whatever the technician typed - "10.2", "Negative",
     * "Yellow / Clear" or ''. A parameter is treated as QUALITATIVE when
     * either the value is not numeric or the parameter carries no numeric
     * interval; in that case $referenceText ("Negative") is what normal
     * means, and anything else is ABNORMAL. A qualitative parameter with
     * no reference text at all can only ever be NORMAL, because nothing
     * has been stated for the value to disagree with - the alternative,
     * guessing, would flag a free-text morphology comment as pathological.
     */
    public static function evaluate(
        string $value,
        ?float $referenceMin,
        ?float $referenceMax,
        ?string $referenceText = null,
    ): self {
        $value = trim($value);

        if ($value === '') {
            return self::NORMAL;
        }

        if (!is_numeric($value) || ($referenceMin === null && $referenceMax === null)) {
            return self::qualitative($value, $referenceText);
        }

        $numeric = (float) $value;

        if ($referenceMin !== null && $numeric < $referenceMin) {
            return self::deviation($referenceMin - $numeric, $referenceMin)
                ? self::CRITICAL_LOW
                : self::LOW;
        }

        if ($referenceMax !== null && $numeric > $referenceMax) {
            return self::deviation($numeric - $referenceMax, $referenceMax)
                ? self::CRITICAL_HIGH
                : self::HIGH;
        }

        return self::NORMAL;
    }

    private static function qualitative(string $value, ?string $referenceText): self
    {
        $normalised = mb_strtolower($value);

        // Read before the reference-text comparison: a dipstick reported
        // as "Positive" is abnormal whether or not anyone remembered to
        // fill in what negative looks like.
        foreach (['positive', 'reactive', 'abnormal', 'detected'] as $marker) {
            if (str_contains($normalised, $marker)) {
                // "Non-reactive" and "not detected" contain their own
                // marker word, so the negating prefix has to win.
                if (preg_match('/\b(non|not|no)[\s-]*' . $marker . '/u', $normalised) === 1) {
                    return self::NORMAL;
                }

                return self::ABNORMAL;
            }
        }

        if ($referenceText === null || trim($referenceText) === '') {
            return self::NORMAL;
        }

        return $normalised === mb_strtolower(trim($referenceText))
            ? self::NORMAL
            : self::ABNORMAL;
    }

    /**
     * Is $excess past the bound more than CRITICAL_DEVIATION of it?
     *
     * A bound of exactly zero (a reference of 0-34 for an antibody titre)
     * has no meaningful percentage, so magnitude is never escalated off
     * it - the result is simply HIGH. Dividing anyway would make every
     * detectable value critical.
     */
    private static function deviation(float $excess, float $bound): bool
    {
        $bound = abs($bound);

        if ($bound === 0.0) {
            return false;
        }

        return ($excess / $bound) > self::CRITICAL_DEVIATION;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::NORMAL, self::LOW, self::HIGH, self::CRITICAL_LOW, self::CRITICAL_HIGH, self::ABNORMAL];
    }
}
