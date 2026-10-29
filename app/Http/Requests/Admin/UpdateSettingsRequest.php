<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', Rule::enum(Currency::class)],
            'timezone' => ['required', 'timezone:all'],
            // Empty means customers can't cancel or reschedule online; only admins can.
            'cancellation_cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            // Empty turns online payment off. Only gateways whose keys are in .env can be chosen.
            'payment_gateway' => ['nullable', Rule::enum(PaymentGatewayName::class)->only(
                array_filter(PaymentGatewayName::cases(), fn (PaymentGatewayName $gateway) => $gateway->isConfigured()),
            )],
            // Empty uses the default.
            'payment_hold_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_gateway.enum' => 'Add this gateway\'s keys to the server settings (.env) before choosing it.',
        ];
    }
}
