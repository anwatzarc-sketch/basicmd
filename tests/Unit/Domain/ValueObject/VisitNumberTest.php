<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Unit\Domain\ValueObject;

use MediCareMini\Domain\ValueObject\VisitNumber;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VisitNumberTest extends TestCase
{
    public function test_formats_the_exact_frs_example(): void
    {
        // FRS 5.5 gives VIS-20260915-00001 as the worked example.
        $date = new DateTimeImmutable('2026-09-15');

        self::assertSame('VIS-20260915-00001', VisitNumber::forDate($date, 1)->value);
    }

    public function test_zero_pads_the_sequence(): void
    {
        $date = new DateTimeImmutable('2026-01-05');

        self::assertSame('VIS-20260105-00099', VisitNumber::forDate($date, 99)->value);
    }

    public function test_round_trips_through_from_string(): void
    {
        $date     = new DateTimeImmutable('2026-09-15');
        $original = VisitNumber::forDate($date, 3);
        $rebuilt  = VisitNumber::fromString($original->value);

        self::assertTrue($original->equals($rebuilt));
    }

    public function test_from_string_tolerates_lowercase(): void
    {
        self::assertSame('VIS-20260915-00001', VisitNumber::fromString('vis-20260915-00001')->value);
    }

    public function test_from_string_rejects_malformed_input(): void
    {
        foreach (['VIS-2026915-00001', 'VIS-20260915-1', 'AMC-20260915-00001', ''] as $bad) {
            $this->expectException(InvalidArgumentException::class);
            VisitNumber::fromString($bad);
        }
    }

    public function test_try_from_returns_null_instead_of_throwing(): void
    {
        self::assertNull(VisitNumber::tryFrom('garbage'));
        self::assertNotNull(VisitNumber::tryFrom('VIS-20260915-00001'));
    }
}
