<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
use App\Enums\PaymentGatewayName;
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
        ]);
    }

    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->setCurrency(Currency::from($request->validated('currency')));
        $settings->setTimezone($request->validated('timezone'));
        $settings->setCancellationCutoffHours($request->filled('cancellation_cutoff_hours') ? (int) $request->validated('cancellation_cutoff_hours') : null);
        $settings->setPaymentGateway(PaymentGatewayName::tryFrom((string) $request->validated('payment_gateway')));
        $settings->setPaymentHoldMinutes($request->filled('payment_hold_minutes') ? (int) $request->validated('payment_hold_minutes') : null);

        return to_route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
