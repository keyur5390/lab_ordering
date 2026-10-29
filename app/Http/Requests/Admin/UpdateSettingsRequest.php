<?php

namespace App\Http\Requests\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use App\Enums\SmsDriver;
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
            'sms_driver.enum' => 'Add this provider\'s keys to the server settings (.env) before choosing it.',
        ];
    }
}
