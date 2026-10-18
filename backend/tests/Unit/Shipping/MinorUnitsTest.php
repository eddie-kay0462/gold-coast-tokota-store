<?php

namespace Tests\Unit\Shipping;

use App\Support\MinorUnits;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MinorUnitsTest extends TestCase
{
    public function test_carrier_decimal_strings_become_exact_minor_units(): void
    {
        $this->assertSame(4_550, MinorUnits::fromDecimal('45.5000'));
        $this->assertSame(4_500, MinorUnits::fromDecimal('45'));
        $this->assertSame(1, MinorUnits::fromDecimal('0.01'));
        $this->assertSame(0, MinorUnits::fromDecimal('0.0000'));
        // A float would make this 1_999 or 2_000 depending on luck.
        $this->assertSame(2_000, MinorUnits::fromDecimal('19.9950'));
        $this->assertSame(1_999, MinorUnits::fromDecimal('19.9949'));
    }

    public function test_it_refuses_anything_that_is_not_a_plain_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MinorUnits::fromDecimal('-5.00');
    }

    public function test_minor_units_format_back_to_a_decimal(): void
    {
        $this->assertSame('45.50', MinorUnits::toDecimal(4_550));
        $this->assertSame('0.05', MinorUnits::toDecimal(5));
    }
}
