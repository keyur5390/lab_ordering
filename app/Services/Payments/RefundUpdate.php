<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;

/** A gateway's news about a refund it accepted earlier (B-25). */
final readonly class RefundUpdate
{
    public function __construct(
        public string $gatewayRefundId,
        public RefundStatus $status,
    ) {}
}
