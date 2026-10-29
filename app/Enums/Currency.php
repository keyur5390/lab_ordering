<?php

namespace App\Enums;

/**
 * Currencies the admin can choose as the site's single active currency (Admin → Settings).
 * All have 2 minor-unit digits, so switching currency never changes how stored amounts are scaled.
 *
 * TODO(client): confirm which currencies should be offered.
 */
enum Currency: string
{
    case AED = 'AED';
    case AUD = 'AUD';
    case CAD = 'CAD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case INR = 'INR';
    case SGD = 'SGD';
    case USD = 'USD';

    /** Number of minor-unit digits (e.g. 2 for paise/cents). */
    public function decimals(): int
    {
        return 2;
    }

    public function label(): string
    {
        return match ($this) {
            self::AED => 'UAE dirham',
            self::AUD => 'Australian dollar',
            self::CAD => 'Canadian dollar',
            self::EUR => 'Euro',
            self::GBP => 'British pound',
            self::INR => 'Indian rupee',
            self::SGD => 'Singapore dollar',
            self::USD => 'US dollar',
        };
    }
}
