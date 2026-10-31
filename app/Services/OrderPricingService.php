<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ServiceArea;
use App\Settings\Settings;

/**
 * What an order costs (PROJECT.md §4, client 2026-10-30), worked out the same way for the checkout
 * summary and when the order is placed:
 *
 *   tests total − coupon discount + collection fee (per area; free from the admin-set value)
 *   = taxable amount; tax at the admin-set rate is either included in it or added on top.
 */
class OrderPricingService
{
    public function __construct(
        private CouponService $coupons,
        private Settings $settings,
    ) {}

    /**
     * @param  int  $subtotal  tests total, minor units
     * @param  Coupon|null  $coupon  already checked with CouponService::problem()
     * @return array{subtotal: int, discount: int, collection_fee: int, collection_fee_waived: bool, tax_rate_basis_points: int|null, tax_inclusive: bool, tax: int, total: int}
     */
    public function quote(int $subtotal, ?ServiceArea $area, ?Coupon $coupon = null): array
    {
        $discount = $coupon ? $this->coupons->discount($coupon, $subtotal) : 0;
        $afterDiscount = $subtotal - $discount;

        // TODO(client): the free-collection value is compared with the total after the coupon.
        $freeAbove = $this->settings->freeCollectionAbove();
        $areaFee = $area?->collection_fee_amount ?? 0;
        $waived = $areaFee > 0 && $freeAbove !== null && $afterDiscount >= $freeAbove;
        $fee = $waived ? 0 : $areaFee;

        $taxable = $afterDiscount + $fee;
        $rate = $this->settings->taxRateBasisPoints();
        $inclusive = $this->settings->taxInclusive();
        $tax = match (true) {
            $rate === null => 0,
            // The part of the price that is tax, rounded half-up.
            $inclusive => $taxable - intdiv($taxable * 10000 + intdiv(10000 + $rate, 2), 10000 + $rate),
            default => intdiv($taxable * $rate + 5000, 10000),
        };

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'collection_fee' => $fee,
            'collection_fee_waived' => $waived,
            'tax_rate_basis_points' => $rate,
            'tax_inclusive' => $inclusive,
            'tax' => $tax,
            'total' => $taxable + ($inclusive ? 0 : $tax),
        ];
    }
}
