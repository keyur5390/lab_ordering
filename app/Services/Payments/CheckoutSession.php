<?php

namespace App\Services\Payments;

final readonly class CheckoutSession
{
    public function __construct(
        /** The gateway's checkout id, stored as the payment's reference. */
        public string $reference,
        public string $url,
    ) {}
}
