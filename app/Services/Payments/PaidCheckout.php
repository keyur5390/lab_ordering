<?php

namespace App\Services\Payments;

final readonly class PaidCheckout
{
    public function __construct(
        /** The gateway's checkout id (the payment's reference). */
        public string $reference,
        public string $gatewayPaymentId,
    ) {}
}
