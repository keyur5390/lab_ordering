<?php

namespace App\Enums;

/**
 * How a collector took payment for a pay-at-collection order (client, 2026-10-30).
 * TODO(client): confirm the methods offered.
 */
enum CollectionPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Upi = 'upi';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Upi => 'UPI',
            self::Other => 'Other',
        };
    }
}
