<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Settings\Settings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Promo codes (client, 2026-10-30).
 */
class CouponController extends Controller
{
    public function __construct(
        private CouponService $coupons,
        private Settings $settings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/coupons/index', [
            'coupons' => Coupon::orderBy('code')->get()->map(fn (Coupon $coupon) => [
                ...$this->form($coupon),
                'id' => $coupon->id,
                'uses' => $this->coupons->uses($coupon),
                'discount_label' => $coupon->discount_type === DiscountType::Percent
                    ? "{$coupon->discount_value}% off"
                    : Money::format($coupon->discount_value, $this->currency()).' off',
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/form', ['coupon' => null]);
    }

    public function store(SaveCouponRequest $request): RedirectResponse
    {
        Coupon::create($request->couponAttributes());

        return to_route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/form', ['coupon' => ['id' => $coupon->id, ...$this->form($coupon)]]);
    }

    public function update(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->couponAttributes());

        return to_route('admin.coupons.index')->with('status', 'Coupon saved.');
    }

    /** Used coupons stay for the order history; switch them off instead. */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->orders()->exists()) {
            return back()->withErrors(['delete' => 'This coupon has been used, so it can\'t be deleted. Switch it off instead.']);
        }

        $coupon->delete();

        return to_route('admin.coupons.index')->with('status', 'Coupon deleted.');
    }

    /**
     * Form values: money as decimals in the site currency.
     *
     * @return array<string, mixed>
     */
    private function form(Coupon $coupon): array
    {
        $currency = $this->settings->currency();
        $decimal = fn (?int $minor) => $minor === null ? null : Money::toDecimal($minor, $currency);

        return [
            'code' => $coupon->code,
            'description' => $coupon->description,
            'discount_type' => $coupon->discount_type->value,
            'discount_value' => $coupon->discount_type === DiscountType::Percent ? (string) $coupon->discount_value : $decimal($coupon->discount_value),
            'max_discount' => $decimal($coupon->max_discount_amount),
            'min_order' => $decimal($coupon->min_order_amount),
            'starts_on' => $coupon->starts_on?->toDateString(),
            'ends_on' => $coupon->ends_on?->toDateString(),
            'usage_limit' => $coupon->usage_limit,
            'per_customer_limit' => $coupon->per_customer_limit,
            'is_active' => $coupon->is_active,
        ];
    }

    private function currency(): string
    {
        return $this->settings->currency()->value ?? 'INR';
    }
}
