<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\Support\Currency;

class CurrencyTest extends TestCase
{
    /** @test */
    public function it_validates_supported_currencies(): void
    {
        $this->assertTrue(Currency::isSupported('USD'));
        $this->assertTrue(Currency::isSupported('EUR'));
        $this->assertTrue(Currency::isSupported('GBP'));
        $this->assertTrue(Currency::isSupported('TRY'));
        $this->assertTrue(Currency::isSupported('CAD'));
        $this->assertTrue(Currency::isSupported('AUD'));
        $this->assertTrue(Currency::isSupported('JPY'));
    }

    /** @test */
    public function it_validates_case_insensitive_currencies(): void
    {
        $this->assertTrue(Currency::isSupported('usd'));
        $this->assertTrue(Currency::isSupported('eur'));
        $this->assertTrue(Currency::isSupported('Gbp'));
        $this->assertTrue(Currency::isSupported('TrY'));
    }

    /** @test */
    public function it_rejects_unsupported_currencies(): void
    {
        $this->assertFalse(Currency::isSupported('INVALID'));
        $this->assertFalse(Currency::isSupported('XYZ'));
        $this->assertFalse(Currency::isSupported(''));
        $this->assertFalse(Currency::isSupported('123'));
    }

    /** @test */
    public function it_returns_supported_currencies_list(): void
    {
        $currencies = Currency::getSupportedCurrencies();

        $this->assertIsArray($currencies);
        $this->assertNotEmpty($currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertContains('TRY', $currencies);
    }

    /** @test */
    public function it_returns_currencies_in_uppercase(): void
    {
        $currencies = Currency::getSupportedCurrencies();

        foreach ($currencies as $currency) {
            $this->assertEquals(strtoupper($currency), $currency);
            $this->assertEquals(3, strlen($currency)); // ISO 4217 standard
        }
    }

    /** @test */
    public function it_has_minimum_required_currencies(): void
    {
        $currencies = Currency::getSupportedCurrencies();
        $requiredCurrencies = ['USD', 'EUR', 'GBP', 'TRY'];

        foreach ($requiredCurrencies as $required) {
            $this->assertContains($required, $currencies, "Missing required currency: {$required}");
        }
    }

    /** @test */
    public function it_returns_correct_minor_units_for_zero_decimal_currencies(): void
    {
        $this->assertEquals(0, Currency::getMinorUnits('JPY'));
        $this->assertEquals(0, Currency::getMinorUnits('KRW'));
    }

    /** @test */
    public function it_returns_correct_minor_units_for_standard_currencies(): void
    {
        $this->assertEquals(2, Currency::getMinorUnits('USD'));
        $this->assertEquals(2, Currency::getMinorUnits('EUR'));
        $this->assertEquals(2, Currency::getMinorUnits('TRY'));
        $this->assertEquals(2, Currency::getMinorUnits('GBP'));
    }

    /** @test */
    public function it_formats_amounts_correctly_for_zero_decimal_currencies(): void
    {
        $this->assertEquals('1,000 JPY', Currency::formatAmount(1000, 'JPY'));
        $this->assertEquals('500 KRW', Currency::formatAmount(500, 'KRW'));
    }

    /** @test */
    public function it_formats_amounts_correctly_for_standard_currencies(): void
    {
        $this->assertEquals('10.00 USD', Currency::formatAmount(1000, 'USD'));
        $this->assertEquals('25.99 EUR', Currency::formatAmount(2599, 'EUR'));
        $this->assertEquals('100.50 TRY', Currency::formatAmount(10050, 'TRY'));
    }

    /** @test */
    public function it_handles_case_insensitive_currency_for_minor_units(): void
    {
        $this->assertEquals(0, Currency::getMinorUnits('jpy'));
        $this->assertEquals(2, Currency::getMinorUnits('usd'));
        $this->assertEquals(2, Currency::getMinorUnits('Eur'));
    }

    /** @test */
    public function it_handles_case_insensitive_currency_for_formatting(): void
    {
        $this->assertEquals('10.00 USD', Currency::formatAmount(1000, 'usd'));
        $this->assertEquals('1,000 JPY', Currency::formatAmount(1000, 'jpy'));
    }
}