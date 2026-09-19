<?php

declare(strict_types=1);

namespace MediCareMini\Tests\Unit\Domain\ValueObject;

use MediCareMini\Domain\ValueObject\PatientId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PatientIdTest extends TestCase
{
    public function test_formats_the_exact_frs_example(): void
    {
        // FRS 5.2 gives PID-2026-00001 as the worked example.
        self::assertSame('PID-2026-00001', PatientId::forYear(2026, 1)->value);
    }

    public function test_zero_pads_the_sequence_to_five_digits(): void
    {
        self::assertSame('PID-2026-00042', PatientId::forYear(2026, 42)->value);
        self::assertSame('PID-2026-99999', PatientId::forYear(2026, 99999)->value);
    }

    public function test_rejects_a_sequence_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PatientId::forYear(2026, 100000);
    }

    public function test_rejects_a_zero_or_negative_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PatientId::forYear(2026, 0);
    }

    public function test_round_trips_through_from_string(): void
    {
        $original = PatientId::forYear(2026, 7);
        $rebuilt  = PatientId::fromString($original->value);

        self::assertTrue($original->equals($rebuilt));
    }

    public function test_from_string_tolerates_lowercase_and_surrounding_whitespace(): void
    {
        $id = PatientId::fromString('  pid-2026-00001  ');

        self::assertSame('PID-2026-00001', $id->value);
    }

    public function test_from_string_rejects_malformed_input(): void
    {
        foreach (['', 'PID-2026-1', 'PID-26-00001', 'AMC-2026-00001', 'PID-2026-000001', 'not a pid'] as $bad) {
            $this->expectException(InvalidArgumentException::class);
            PatientId::fromString($bad);
        }
    }

    public function test_try_from_returns_null_instead_of_throwing(): void
    {
        self::assertNull(PatientId::tryFrom('garbage'));
        self::assertNotNull(PatientId::tryFrom('PID-2026-00001'));
    }

    public function test_two_different_ids_are_not_equal(): void
    {
        self::assertFalse(PatientId::forYear(2026, 1)->equals(PatientId::forYear(2026, 2)));
    }
}
