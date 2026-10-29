<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use App\Enums\SmsDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('admin/settings', [
            'currency' => $settings->currency()?->value,
            'currencies' => array_map(
                fn (Currency $currency) => ['value' => $currency->value, 'label' => "{$currency->value} · {$currency->label()}"],
                Currency::cases(),
            ),
            'timezone' => $settings->timezone(),
            'timezones' => timezone_identifiers_list(),
            'cancellationCutoffHours' => $settings->cancellationCutoffHours(),
            'paymentGateway' => $settings->paymentGateway()?->value,
            'gateways' => array_map(fn (PaymentGatewayName $gateway) => [
                'value' => $gateway->value,
                'label' => $gateway->label(),
                'configured' => $gateway->isConfigured(),
                'env_keys' => $gateway->envKeys(),
            ], PaymentGatewayName::cases()),
            'paymentHoldMinutes' => $settings->paymentHoldMinutes(),
            'notifyByEmail' => $settings->notifyByEmail(),
            'smsDriver' => $settings->smsDriver()?->value,
            'smsDrivers' => array_map(fn (SmsDriver $driver) => [
                'value' => $driver->value,
                'label' => $driver->label(),
                'configured' => $driver->isConfigured(),
                'env_keys' => $driver->envKeys(),
            ], SmsDriver::cases()),
            'reminderHoursBefore' => $settings->reminderHoursBefore(),
            'adminNotificationEmail' => $settings->adminNotificationEmail(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->setCurrency(Currency::from($request->validated('currency')));
        $settings->setTimezone($request->validated('timezone'));
        $settings->setCancellationCutoffHours($request->filled('cancellation_cutoff_hours') ? (int) $request->validated('cancellation_cutoff_hours') : null);
        $settings->setPaymentGateway(PaymentGatewayName::tryFrom((string) $request->validated('payment_gateway')));
        if ($request->has('notify_by_email')) {
            $settings->setNotifyByEmail($request->boolean('notify_by_email'));
        }
        $settings->setSmsDriver(SmsDriver::tryFrom((string) $request->validated('sms_driver')));
        $settings->setReminderHoursBefore($request->filled('reminder_hours_before') ? (int) $request->validated('reminder_hours_before') : null);
        $settings->setAdminNotificationEmail($request->filled('admin_notification_email') ? strtolower($request->validated('admin_notification_email')) : null);
        $settings->setPaymentHoldMinutes($request->filled('payment_hold_minutes') ? (int) $request->validated('payment_hold_minutes') : null);

        return to_route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
