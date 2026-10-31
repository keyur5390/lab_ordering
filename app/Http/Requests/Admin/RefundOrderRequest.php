<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin refund, typed as a decimal in the order's currency. The upper limit (what is still
 * refundable) is checked by PaymentService::refund().
 */
class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()->can('refund', $order);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:'.Money::decimalPattern($this->currency())],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter an amount like 250 or 250.50.',
        ];
    }

    public function amountMinor(): int
    {
        return Money::toMinor($this->validated('amount'), $this->currency());
    }

    private function currency(): ?Currency
    {
        /** @var Order $order */
        $order = $this->route('order');

        return Currency::tryFrom($order->currency);
    }
}
