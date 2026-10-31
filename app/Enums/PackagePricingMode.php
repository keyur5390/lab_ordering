<?php

namespace App\Enums;

enum PackagePricingMode: string
{
    /** The package has its own price. */
    case Fixed = 'fixed';

    /** Price is the sum of the package's tests minus a discount. */
    case Discounted = 'discounted';
}
