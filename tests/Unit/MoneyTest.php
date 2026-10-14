<?php

namespace Tests\Unit;

use App\Services\Finance\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_currency_is_normalized_without_floating_point_arithmetic(): void
    {
        $this->assertSame('100.00', Money::normalize('100'));
        $this->assertSame('0.01', Money::normalize('0,01', true));
        $this->assertSame('9999999999999.99', Money::normalize('9999999999999.99'));
        $this->assertSame('10,23', Money::format('10.23'));
    }
}
