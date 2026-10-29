<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

/**
 * Converts between admin-entered decimal strings ("499.50") and stored integer minor units (49950).
 * Display formatting happens on the frontend (resources/js/lib/money.ts).
 */
final class Money
{
    /** Regex for a valid decimal amount in the given currency, for validation rules. */
    public static function decimalPattern(?Currency $currency): string
    {
        $decimals = $currency?->decimals() ?? 2;

        return '/^\d{1,12}(\.\d{1,'.$decimals.'})?$/';
    }

    public static function toMinor(string $decimal, ?Currency $currency): int
    {
        $decimals = $currency?->decimals() ?? 2;

        if (! preg_match(self::decimalPattern($currency), $decimal)) {
            throw new InvalidArgumentException("Invalid amount [{$decimal}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return (int) $whole * 10 ** $decimals + (int) str_pad($fraction, $decimals, '0');
    }

    public static function toDecimal(int $minor, ?Currency $currency): string
    {
        $decimals = $currency?->decimals() ?? 2;

        return number_format($minor / 10 ** $decimals, $decimals, '.', '');
    }
}
