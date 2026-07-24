<?php

namespace Tests\Unit\Banking;

use App\Domain\Banking\Services\DecimalMoney;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    public function test_it_calculates_money_with_minor_units_without_float_rounding(): void
    {
        $this->assertSame('0.30', DecimalMoney::add('0.10', '0.20'));
        $this->assertSame('999999999999.99', DecimalMoney::subtract('1000000000000.00', '0.01'));
        $this->assertSame(-1, DecimalMoney::compare('10.01', '10.02'));
        $this->assertSame('12.30', DecimalMoney::normalize('12,3'));
    }

    public function test_it_rejects_more_than_two_fraction_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::normalize('10.001');
    }

    public function test_it_rejects_float_inputs_at_the_type_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::normalize(0.1);
    }
}
