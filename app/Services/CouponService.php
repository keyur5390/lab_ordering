<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\User;
use App\Settings\Settings;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Promo codes (client, 2026-10-30): percent (with an optional cap) or a fixed amount off the tests
 * total, with optional dates, usage limits and a minimum order value.
 */
class CouponService
{
    public function __construct(
        private PackagePricingService $pricing,
        private Settings $settings,
    ) {}

    public function find(string $code): ?Coupon
    {
        return Coupon::where('code', Coupon::normalise($code))->first();
    }

    /**
     * Why the coupon can't be used on this order, or null when it can.
     *
     * @param  int  $subtotal  tests total, minor units
     */
    public function problem(Coupon $coupon, User $user, int $subtotal, ?CarbonImmutable $now = null): ?string
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone($this->settings->timezone())->toDateString();

        return match (true) {
            ! $coupon->is_active, $coupon->ends_on && $coupon->ends_on->toDateString() < $today => 'This code has expired.',
            $coupon->starts_on && $coupon->starts_on->toDateString() > $today => 'This code isn\'t valid yet.',
            $coupon->min_order_amount !== null && $subtotal < $coupon->min_order_amount => 'Spend at least '.Money::format($coupon->min_order_amount, $this->currency()).' to use this code.',
            $coupon->usage_limit !== null && $this->uses($coupon) >= $coupon->usage_limit => 'This code has been fully used.',
            $coupon->per_customer_limit !== null && $this->uses($coupon, $user) >= $coupon->per_customer_limit => 'You\'ve already used this code.',
            default => null,
        };
    }

    /** Amount off the tests total, never more than the total. */
    public function discount(Coupon $coupon, int $subtotal): int
    {
        $discount = $this->pricing->discount($subtotal, $coupon->discount_type, $coupon->discount_value);

        if ($coupon->discount_type === DiscountType::Percent && $coupon->max_discount_amount !== null) {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        return min($discount, $subtotal);
    }

    /**
     * Orders that used the coupon. Cancelled and refunded orders give their use back.
     * TODO(client): confirm that a cancelled order frees the use.
     */
    public function uses(Coupon $coupon, ?User $user = null): int
    {
        return $coupon->orders()
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->count();
    }

    private function currency(): string
    {
        return $this->settings->currency()->value ?? 'INR';
    }
}
