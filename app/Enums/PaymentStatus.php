<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Checkout created at the gateway; the customer hasn't paid yet. */
    case Pending = 'pending';
    case Paid = 'paid';
    /** The gateway checkout couldn't be created. */
    case Failed = 'failed';
}
