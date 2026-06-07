<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_exact_format_uses_rs_and_grouping(): void
    {
        $this->assertSame('Rs 1,234,567.00', Money::format(1234567));
        $this->assertSame('Rs 0.00', Money::format(0));
        $this->assertSame('1,000.50', Money::format(1000.5, 2, false));
    }

    public function test_short_format_thousand(): void
    {
        $this->assertSame('Rs 1K', Money::short(1000));
        $this->assertSame('Rs 1.5K', Money::short(1500));
        $this->assertSame('Rs 999', Money::short(999));
    }

    public function test_short_format_lac(): void
    {
        $this->assertSame('Rs 1 Lac', Money::short(100000));
        $this->assertSame('Rs 1.5 Lac', Money::short(150000));
    }

    public function test_short_format_crore(): void
    {
        $this->assertSame('Rs 1 Crore', Money::short(10000000));
        $this->assertSame('Rs 2.5 Crore', Money::short(25000000));
    }

    public function test_negative_amounts(): void
    {
        $this->assertSame('Rs -1.5 Lac', Money::short(-150000));
    }

    public function test_paisa_round_trip_is_exact(): void
    {
        $this->assertSame(123456, Money::toPaisa(1234.56));
        $this->assertSame(1234.56, Money::toRupees(123456));
        // No float drift: 0.1 + 0.2 in paisa
        $this->assertSame(30, Money::toPaisa(0.1) + Money::toPaisa(0.2));
    }
}
