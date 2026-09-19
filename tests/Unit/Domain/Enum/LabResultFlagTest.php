<?php

declare(strict_types=1);

namespace Aster\Tests\Unit\Domain\Enum;

use Aster\Domain\Enum\LabResultFlag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The flag engine decides what a printed report calls abnormal, so the
 * boundaries are pinned here rather than left to the entry screen to
 * demonstrate by example.
 */
final class LabResultFlagTest extends TestCase
{
    /** @return list<array{string, ?float, ?float, ?string, LabResultFlag}> */
    public static function values(): array
    {
        return [
            // Inside the interval, and exactly on either bound - a value
            // equal to a bound is normal, not borderline.
            ['14',   12.0, 16.0, null, LabResultFlag::NORMAL],
            ['12',   12.0, 16.0, null, LabResultFlag::NORMAL],
            ['16',   12.0, 16.0, null, LabResultFlag::NORMAL],

            // Outside, but within 25% of the bound.
            ['10.2', 12.0, 16.0, null, LabResultFlag::LOW],
            ['9.1',  12.0, 16.0, null, LabResultFlag::LOW],
            ['19',   12.0, 16.0, null, LabResultFlag::HIGH],

            // More than 25% beyond: 12 * 0.75 = 9, 16 * 1.25 = 20.
            ['8.9',  12.0, 16.0, null, LabResultFlag::CRITICAL_LOW],
            ['20.1', 12.0, 16.0, null, LabResultFlag::CRITICAL_HIGH],
            ['9',    12.0, 16.0, null, LabResultFlag::LOW],
            ['20',   12.0, 16.0, null, LabResultFlag::HIGH],

            // Open-ended intervals: only the stated bound is checked.
            ['500',  null, 34.0, null, LabResultFlag::CRITICAL_HIGH],
            ['1',    60.0, null, null, LabResultFlag::CRITICAL_LOW],
            ['80',   60.0, null, null, LabResultFlag::NORMAL],

            // A bound of exactly zero has no percentage to exceed, so a
            // detectable value is HIGH and never escalates to critical.
            ['12',   null, 0.0,  null, LabResultFlag::HIGH],

            // 0-0 is a REAL interval here ("expected to be zero"), not the
            // "no interval" sentinel the prototype used - a qualitative
            // parameter carries NULL bounds instead (see migration 012).
            // So a detectable value against 0-0 is a finding, not a
            // silently normal result.
            ['12',   0.0,  0.0,  null, LabResultFlag::HIGH],
            ['0',    0.0,  0.0,  null, LabResultFlag::NORMAL],

            // Qualitative: the reference text is what normal reads as.
            ['Negative',     null, null, 'Negative', LabResultFlag::NORMAL],
            ['Trace',        null, null, 'Negative', LabResultFlag::ABNORMAL],
            ['negative',     null, null, 'Negative', LabResultFlag::NORMAL],
            ['Yellow / Clear', null, null, 'Yellow / Clear', LabResultFlag::NORMAL],

            // Marker words win over the reference text, and a negating
            // prefix wins over the marker.
            ['Positive',      null, null, null, LabResultFlag::ABNORMAL],
            ['Reactive',      null, null, null, LabResultFlag::ABNORMAL],
            ['Non-reactive',  null, null, null, LabResultFlag::NORMAL],
            ['Not detected',  null, null, null, LabResultFlag::NORMAL],
            ['Positive',      null, null, 'Positive', LabResultFlag::ABNORMAL],

            // With no reference text there is nothing to disagree with, so
            // free text is never invented into a finding.
            ['Occasional epithelial cells', null, null, null, LabResultFlag::NORMAL],

            // Text where a number was expected falls to the qualitative
            // path rather than parsing as 0.
            ['insufficient sample', 12.0, 16.0, null, LabResultFlag::NORMAL],

            // Nothing entered is not a result.
            ['',   12.0, 16.0, null, LabResultFlag::NORMAL],
            ['  ', 12.0, 16.0, null, LabResultFlag::NORMAL],
        ];
    }

    #[DataProvider('values')]
    public function test_evaluates_a_value_against_its_interval(
        string $value,
        ?float $min,
        ?float $max,
        ?string $text,
        LabResultFlag $expected,
    ): void {
        self::assertSame($expected, LabResultFlag::evaluate($value, $min, $max, $text));
    }

    public function test_only_normal_is_not_abnormal(): void
    {
        foreach (LabResultFlag::all() as $flag) {
            self::assertSame($flag !== LabResultFlag::NORMAL, $flag->isAbnormal(), $flag->value);
        }
    }

    public function test_only_the_two_critical_cases_are_critical(): void
    {
        $critical = array_values(array_filter(
            LabResultFlag::all(),
            static fn (LabResultFlag $flag): bool => $flag->isCritical(),
        ));

        self::assertSame([LabResultFlag::CRITICAL_LOW, LabResultFlag::CRITICAL_HIGH], $critical);
    }

    public function test_every_flag_has_a_label_and_a_chip_class(): void
    {
        foreach (LabResultFlag::all() as $flag) {
            self::assertNotSame('', $flag->chipClass());
            self::assertNotSame('', $flag->label());
            self::assertNotSame('', $flag->shortLabel());
        }
    }
}
