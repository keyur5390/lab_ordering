<?php

namespace App\Enums;

enum PaymentMethod: string
{
    /** Paid to the collector (client, 2026-10-29: until online payments are added). */
    case PayAtCollection = 'pay_at_collection';

    public function label(): string
    {
        return match ($this) {
            self::PayAtCollection => 'Pay at collection',
        };
    }
}
