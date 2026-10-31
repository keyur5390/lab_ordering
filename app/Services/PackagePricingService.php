<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PackagePricingMode;
use App\Models\LabPackage;

/**
 * Works out what a package costs (PROJECT.md §3). Prices are derived on read so a
 * discounted package always follows its tests' current prices.
 */
class PackagePricingService
{
    /** Sum of the package's tests' prices, in minor units. */
    public function testsTotal(LabPackage $package): int
    {
        return (int) $package->tests->sum('price_amount');
    }

    /** What the customer pays for the package, in minor units. */
    public function price(LabPackage $package): int
    {
        if ($package->pricing_mode === PackagePricingMode::Fixed) {
            return (int) $package->price_amount;
        }

        $total = $this->testsTotal($package);

        return max(0, $total - $this->discount($total, $package->discount_type, (int) $package->discount_value));
    }

    /**
     * Discount off a total. A percent discount is rounded half-up to the nearest minor unit.
     * TODO(client): confirm the rounding rule.
     */
    public function discount(int $total, ?DiscountType $type, int $value): int
    {
        return match ($type) {
            DiscountType::Percent => intdiv($total * $value + 50, 100),
            DiscountType::Amount => min($value, $total),
            null => 0,
        };
    }

    /**
     * Slowest turnaround among the package's tests, in hours.
     * TODO(client): confirm a package's turnaround is its slowest test.
     */
    public function turnaroundHours(LabPackage $package): int
    {
        return (int) $package->tests->max('turnaround_hours');
    }
}
