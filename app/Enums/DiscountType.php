<?php

namespace App\Enums;

enum DiscountType: string
{
    /** Whole percent off (0–100). */
    case Percent = 'percent';

    /** Fixed amount off, in minor units. */
    case Amount = 'amount';
}
