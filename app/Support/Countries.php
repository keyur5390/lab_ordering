<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Locale;

/**
 * ISO 3166-1 alpha-2 countries with English names, from PHP's intl extension.
 */
final class Countries
{
    /** Region codes intl knows that are not countries (unions, pseudo-locales, unassigned territories). */
    private const NOT_COUNTRIES = ['AC', 'CP', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'ZZ'];

    /**
     * @return array<string, string> code => name, sorted by name
     */
    public static function all(): array
    {
        return Cache::rememberForever('countries.en', function () {
            $countries = [];

            foreach (range('A', 'Z') as $first) {
                foreach (range('A', 'Z') as $second) {
                    $code = $first.$second;
                    $name = Locale::getDisplayRegion('-'.$code, 'en');

                    if ($name !== $code && ! in_array($code, self::NOT_COUNTRIES, true)) {
                        $countries[$code] = $name;
                    }
                }
            }

            asort($countries);

            return $countries;
        });
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function name(string $code): string
    {
        return self::all()[$code] ?? $code;
    }

    /** Postcodes compare without case, spaces or dashes: "sw1a-1aa" matches "SW1A 1AA". */
    public static function normalizePostcode(string $postcode): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $postcode));
    }
}
