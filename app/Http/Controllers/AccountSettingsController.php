<?php

namespace App\Http\Controllers;

use App\Services\PhoneCodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account settings (B-23): name, email and password, and the mobile number (B-30).
 */
class AccountSettingsController extends Controller
{
    public function show(Request $request, PhoneCodeService $codes): Response
    {
        $user = $request->user();

        return Inertia::render('account-settings', [
            'phone' => [
                'available' => $codes->available(),
                'number' => $user->phone_verified_at ? $user->phone : null,
                // A number waiting for its code.
                'pending' => $request->session()->get(AccountPhoneController::SESSION_KEY),
            ],
        ]);
    }
}
