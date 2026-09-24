<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Currency;

class CurrencyHelpersTest extends TestCase
{
    public function test_decimal_count_ignores_insignificant_trailing_zeroes(): void
    {
        $this->assertSame(2, currency_decimal_count('19.99000000'));
        $this->assertSame(2, currency_decimal_count('19.99'));
        $this->assertSame(0, currency_decimal_count('19.00000000'));
        $this->assertSame(4, currency_decimal_count('19.99010000'));
    }

    public function test_currency_input_and_display_follow_configured_precision(): void
    {
        $zeroDecimalCurrency = new Currency(['code' => 'KHR', 'decimal_places' => 0]);
        $threeDecimalCurrency = new Currency(['code' => 'KWD', 'decimal_places' => 3]);

        $this->assertSame('4100', format_currency_input('4100.00000000', $zeroDecimalCurrency));
        $this->assertSame('1.235', format_currency_input('1.2346', $threeDecimalCurrency));
        $this->assertSame('KWD 1.235', format_currency_amount('1.2346', $threeDecimalCurrency));
    }
}
