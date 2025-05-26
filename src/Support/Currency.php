<?php

declare(strict_types=1);

namespace Wio\WioPayments\Support;

class Currency
{
    private const SUPPORTED_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'TRY', 'CAD', 'AUD', 'JPY', 'CHF',
        'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN'
    ];

    public static function isSupported(string $currency): bool
    {
        return in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    public static function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public static function getMinorUnits(string $currency): int
    {
        return match (strtoupper($currency)) {
            'JPY', 'KRW' => 0, // Zero decimal currencies
            default => 2, // Most currencies use 2 decimal places
        };
    }

    public static function formatAmount(int $amountInCents, string $currency): string
    {
        $minorUnits = self::getMinorUnits($currency);
        $amount = $amountInCents / (10 ** $minorUnits);
        
        return number_format($amount, $minorUnits) . ' ' . strtoupper($currency);
    }
}