<?php

declare(strict_types=1);

namespace Aster\Tests\Unit\Domain\ValueObject;

use Aster\Domain\ValueObject\AccessionNumber;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AccessionNumberTest extends TestCase
{
    public function test_formats_as_lab_date_sequence(): void
    {
        $date = new DateTimeImmutable('2026-09-19');

        self::assertSame('LAB-20260919-00001', AccessionNumber::forDate($date, 1)->value);
    }

    public function test_zero_pads_the_sequence(): void
    {
        self::assertSame(
            'LAB-20260105-00099',
            AccessionNumber::forDate(new DateTimeImmutable('2026-01-05'), 99)->value,
        );
    }

    public function test_round_trips_through_from_string(): void
    {
        $issued = AccessionNumber::forDate(new DateTimeImmutable('2026-09-19'), 42);

        self::assertTrue($issued->equals(AccessionNumber::fromString($issued->value)));
        self::assertSame($issued->value, (string) $issued);
    }

    public function test_is_case_insensitive_and_trims(): void
    {
        self::assertSame('LAB-20260919-00042', AccessionNumber::fromString('  lab-20260919-00042 ')->value);
    }

    public function test_the_sequence_scope_is_per_calendar_day(): void
    {
        // Two accessions on the same day must draw from one scope, and a
        // new day must start over - which is what makes the number
        // sequential per day rather than global.
        self::assertSame('accession:20260919', AccessionNumber::scopeFor(new DateTimeImmutable('2026-09-19 23:59')));
        self::assertSame('accession:20260920', AccessionNumber::scopeFor(new DateTimeImmutable('2026-09-20 00:01')));
    }

    public function test_rejects_a_sequence_outside_the_five_digit_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessionNumber::forDate(new DateTimeImmutable('2026-09-19'), 100000);
    }

    public function test_try_from_returns_null_for_anything_malformed(): void
    {
        foreach (['', 'LAB-2026-0001', 'VIS-20260919-00001', 'LAB-20260919-1', 'nonsense'] as $candidate) {
            self::assertNull(AccessionNumber::tryFrom($candidate), $candidate);
        }
    }
}
