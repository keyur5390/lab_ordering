<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Currency;
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
        ]);
    }

    public function update(UpdateSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->setCurrency(Currency::from($request->validated('currency')));
        $settings->setTimezone($request->validated('timezone'));
        $settings->setCancellationCutoffHours($request->filled('cancellation_cutoff_hours') ? (int) $request->validated('cancellation_cutoff_hours') : null);

        return to_route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
