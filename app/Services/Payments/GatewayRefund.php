<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;

final readonly class GatewayRefund
{
    public function __construct(
        public ?string $id,
        public RefundStatus $status,
    ) {}
}
