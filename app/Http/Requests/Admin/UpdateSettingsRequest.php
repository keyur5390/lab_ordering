<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use App\Enums\SmsDriver;
use App\Support\Money;
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
            // Pricing (client, 2026-10-30). Money in the chosen currency; empty turns each off.
            'free_collection_above' => ['nullable', 'string', 'regex:'.Money::decimalPattern(Currency::tryFrom((string) $this->input('currency')))],
            'minimum_order' => ['nullable', 'string', 'regex:'.Money::decimalPattern(Currency::tryFrom((string) $this->input('currency')))],
            // Percent with up to 2 decimals, e.g. 18 or 5.5.
            'tax_rate' => ['nullable', 'numeric', 'regex:/^\d{1,3}(\.\d{1,2})?$/', 'min:0', 'max:100'],
            'tax_inclusive' => ['nullable', 'boolean'],
            // Seller details on invoices (B-21); empty name falls back to the site name.
            'invoice_name' => ['nullable', 'string', 'max:255'],
            'invoice_address' => ['nullable', 'string', 'max:500'],
            'invoice_tax_id' => ['nullable', 'string', 'max:50'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
            'notify_by_email' => ['nullable', 'boolean'],
            // Empty turns SMS off. Only drivers whose keys are in .env can be chosen.
            'sms_driver' => ['nullable', Rule::enum(SmsDriver::class)->only(
                array_filter(SmsDriver::cases(), fn (SmsDriver $driver) => $driver->isConfigured()),
            )],
            // Empty uses the default; 0 turns reminders off.
            'reminder_hours_before' => ['nullable', 'integer', 'min:0', 'max:168'],
            // Empty turns admin alerts off.
            'admin_notification_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_gateway.enum' => 'Add this gateway\'s keys to the server settings (.env) before choosing it.',
            'free_collection_above.regex' => 'Enter an amount like 999 or 999.50.',
            'minimum_order.regex' => 'Enter an amount like 299 or 299.50.',
            'tax_rate.regex' => 'Enter a percentage like 18 or 5.5.',
            'sms_driver.enum' => 'Add this provider\'s keys to the server settings (.env) before choosing it.',
        ];
    }
}
