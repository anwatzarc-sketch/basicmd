<?php

declare(strict_types=1);

namespace Aster\Tests\Unit\Domain\ValueObject;

use Aster\Domain\ValueObject\Balance;
use Aster\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BalanceTest extends TestCase
{
    public function test_can_represent_a_negative_amount_unlike_money(): void
    {
        $balance = Balance::fromMinor(-50000);

        self::assertSame(-50000, $balance->amountMinor);
        self::assertTrue($balance->isNegative());
    }

    public function test_from_database_parses_a_negative_decimal_string(): void
    {
        $balance = Balance::fromDatabase('-1240.00');

        self::assertSame(-124000, $balance->amountMinor);
        self::assertSame('ETB -1,240.00', $balance->format());
    }

    public function test_subtracting_a_larger_amount_produces_a_negative_balance(): void
    {
        // Unlike Money::subtract(), which throws - this is the whole point
        // of Balance existing (FRS 8.3: overpayment is a valid state).
        $charges  = Balance::fromMoney(Money::fromMajor(1000));
        $payments = Balance::fromMoney(Money::fromMajor(1500));

        $balance = $charges->subtract($payments);

        self::assertTrue($balance->isNegative());
        self::assertSame(-50000, $balance->amountMinor);
    }

    public function test_the_frs_clearance_rule_balance_lte_zero(): void
    {
        self::assertTrue(Balance::fromMinor(0)->isSettledOrCredit());
        self::assertTrue(Balance::fromMinor(-1)->isSettledOrCredit());
        self::assertFalse(Balance::fromMinor(1)->isSettledOrCredit());
    }

    public function test_a_reversed_charge_and_its_original_net_to_zero(): void
    {
        $original  = Balance::fromMoney(Money::fromMajor(500));
        $reversal  = $original->negate();

        self::assertTrue($original->add($reversal)->isZero());
    }

    public function test_add_and_subtract_across_a_sequence_of_entries(): void
    {
        // A realistic ledger walk: two charges, a payment, a correction.
        $balance = Balance::zero();
        $balance = $balance->add(Balance::fromMoney(Money::fromMajor(300))); // consultation
        $balance = $balance->add(Balance::fromMoney(Money::fromMajor(1200))); // lab
        $balance = $balance->subtract(Balance::fromMoney(Money::fromMajor(1000))); // payment
        $balance = $balance->add(Balance::fromMinor(-10000)); // 100.00 correction to the lab charge

        self::assertSame(40000, $balance->amountMinor); // 300+1200-1000-100 = 400.00
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Balance::fromMinor(100, 'ETB')->add(Balance::fromMinor(100, 'USD'));
    }

    public function test_from_money_preserves_the_amount_and_currency(): void
    {
        $money   = Money::fromMajor(2500, 'ETB');
        $balance = Balance::fromMoney($money);

        self::assertSame($money->amountMinor, $balance->amountMinor);
        self::assertSame($money->currency, $balance->currency);
    }

    public function test_to_database_round_trips_a_negative_value(): void
    {
        $balance = Balance::fromMinor(-123456);

        self::assertSame('-1234.56', $balance->toDatabase());
        self::assertSame(-123456, Balance::fromDatabase('-1234.56')->amountMinor);
    }
}
