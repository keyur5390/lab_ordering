<?php

namespace App\Enums;

/**
 * How the customer pays; they choose at checkout (client, 2026-10-29: both are always offered
 * while an online gateway is set up).
 */
enum PaymentMethod: string
{
    /** Paid to the collector. */
    case PayAtCollection = 'pay_at_collection';

    /** Paid at checkout through the admin-chosen gateway. */
    case Online = 'online';

    public function isOnline(): bool
    {
        return $this === self::Online;
    }

    public function label(): string
    {
        return match ($this) {
            self::PayAtCollection => 'Pay at collection',
            self::Online => 'Online',
        };
    }
}
