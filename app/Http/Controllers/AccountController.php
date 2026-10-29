<?php

namespace App\Http\Controllers;

use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request, Settings $settings): Response
    {
        $user = $request->user();

        return Inertia::render('account', [
            // Only when SMS is switched on site-wide (client, 2026-10-29).
            'sms' => $settings->smsDriver() ? [
                'opted_out' => $user->sms_opt_out,
                'email' => $settings->notifyByEmail(),
                'phone' => $user->routeNotificationForSms(),
            ] : null,
        ]);
    }

    /** Customers can opt out of SMS; order emails are always sent. */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate(['sms' => ['required', 'boolean']]);
        $request->user()->forceFill(['sms_opt_out' => ! $validated['sms']])->save();

        return to_route('account')->with('status', 'Notification preferences saved.');
    }
}
