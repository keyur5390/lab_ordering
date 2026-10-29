<?php

namespace App\Support;

use App\Enums\Currency;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * Converts between admin-entered decimal strings ("499.50") and stored integer minor units (49950).
 * Pages format on the frontend (resources/js/lib/money.ts); format() is for emails and SMS.
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

    /** For messages, e.g. 49950 INR → "₹499.50". */
    public static function format(int $minor, string $currencyCode): string
    {
        $currency = Currency::tryFrom($currencyCode);
        $decimals = $currency?->decimals() ?? 2;

        return (string) Number::currency($minor / 10 ** $decimals, in: $currencyCode, locale: config('app.locale'));
    }
}
