<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
use App\Enums\SmsDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Settings\Settings;
use App\Support\Money;
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
            'freeCollectionAbove' => $this->decimal($settings->freeCollectionAbove(), $settings),
            'minimumOrder' => $this->decimal($settings->minimumOrderAmount(), $settings),
            'taxRate' => $settings->taxRateBasisPoints() !== null ? rtrim(rtrim(number_format($settings->taxRateBasisPoints() / 100, 2, '.', ''), '0'), '.') : null,
            'taxInclusive' => $settings->taxInclusive(),
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
        $currency = Currency::from($request->validated('currency'));
        $money = fn (string $field) => $request->filled($field) ? Money::toMinor($request->validated($field), $currency) : null;
        $settings->setFreeCollectionAbove($money('free_collection_above'));
        $settings->setMinimumOrderAmount($money('minimum_order'));
        $settings->setTaxRateBasisPoints($request->filled('tax_rate') ? (int) round((float) $request->validated('tax_rate') * 100) : null);
        if ($request->has('tax_inclusive')) {
            $settings->setTaxInclusive($request->boolean('tax_inclusive'));
        }

        if ($request->has('notify_by_email')) {
            $settings->setNotifyByEmail($request->boolean('notify_by_email'));
        }
        $settings->setSmsDriver(SmsDriver::tryFrom((string) $request->validated('sms_driver')));
        $settings->setReminderHoursBefore($request->filled('reminder_hours_before') ? (int) $request->validated('reminder_hours_before') : null);
        $settings->setAdminNotificationEmail($request->filled('admin_notification_email') ? strtolower($request->validated('admin_notification_email')) : null);
        $settings->setPaymentHoldMinutes($request->filled('payment_hold_minutes') ? (int) $request->validated('payment_hold_minutes') : null);

        return to_route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    private function decimal(?int $minor, Settings $settings): ?string
    {
        return $minor === null ? null : Money::toDecimal($minor, $settings->currency());
    }
}
