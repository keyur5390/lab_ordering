<?php

namespace App\Http\Requests\Admin;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Settings\Settings;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Coupon rules (client, 2026-10-30): percent (with an optional cap) or fixed amount, optional dates,
 * usage limits and minimum order. Money is typed as decimals in the site currency.
 */
class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Coupon::normalise((string) $this->input('code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Coupon|null $coupon */
        $coupon = $this->route('coupon');
        $money = ['nullable', 'string', 'regex:'.Money::decimalPattern(app(Settings::class)->currency())];
        $percent = $this->input('discount_type') === DiscountType::Percent->value;

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => $percent
                ? ['required', 'integer', 'min:1', 'max:100']
                : ['required', 'string', 'regex:'.Money::decimalPattern(app(Settings::class)->currency()), 'not_regex:/^0+(\.0+)?$/'],
            'max_discount' => $percent ? $money : ['prohibited'],
            'min_order' => $money,
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'discount_value.regex' => 'Enter an amount like 100 or 99.50.',
            'discount_value.not_regex' => 'Enter an amount above zero.',
            'max_discount.regex' => 'Enter an amount like 500 or 499.50.',
            'min_order.regex' => 'Enter an amount like 999 or 999.50.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function couponAttributes(): array
    {
        $currency = app(Settings::class)->currency();
        $type = DiscountType::from($this->validated('discount_type'));
        $money = fn (string $field) => $this->filled($field) ? Money::toMinor($this->validated($field), $currency) : null;

        return [
            'code' => $this->validated('code'),
            'description' => $this->validated('description'),
            'discount_type' => $type,
            'discount_value' => $type === DiscountType::Percent
                ? (int) $this->validated('discount_value')
                : Money::toMinor($this->validated('discount_value'), $currency),
            'max_discount_amount' => $type === DiscountType::Percent ? $money('max_discount') : null,
            'min_order_amount' => $money('min_order'),
            'starts_on' => $this->validated('starts_on'),
            'ends_on' => $this->validated('ends_on'),
            'usage_limit' => $this->validated('usage_limit'),
            'per_customer_limit' => $this->validated('per_customer_limit'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
