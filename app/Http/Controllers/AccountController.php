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
            // WhatsApp needs the customer's opt-in (B-31).
            'whatsapp' => $settings->whatsAppEnabled() ? [
                'opted_in' => $user->whatsapp_opt_in,
                'phone' => $user->routeNotificationForSms(),
            ] : null,
        ]);
    }

    /** Customers can opt out of SMS and opt in to WhatsApp; order emails are always sent. */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sms' => ['required_without:whatsapp', 'boolean'],
            'whatsapp' => ['required_without:sms', 'boolean'],
        ]);
        $request->user()->forceFill(array_filter([
            'sms_opt_out' => isset($validated['sms']) ? ! $validated['sms'] : null,
            'whatsapp_opt_in' => $validated['whatsapp'] ?? null,
        ], fn ($value) => $value !== null))->save();

        return to_route('account')->with('status', __('Notification preferences saved.'));
    }
}
