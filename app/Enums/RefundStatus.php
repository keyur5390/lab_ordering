<?php

namespace App\Enums;

enum RefundStatus: string
{
    /** Accepted by the gateway, still on its way to the customer. */
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    /** Rejected by the gateway or not sent; an admin can refund again. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Processing',
            self::Succeeded => 'Refunded',
            self::Failed => 'Failed',
        };
    }

    /** Pending and succeeded refunds both count against what can still be refunded. */
    public function counts(): bool
    {
        return $this !== self::Failed;
    }
}
