<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PhoneCodeService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Account settings → Mobile number (B-30): add a number (confirmed by a code), or remove it.
 * A confirmed number can be used to sign in, and gets order texts.
 */
class AccountPhoneController extends Controller
{
    /** Session key: the number a confirmation code was sent to. */
    public const SESSION_KEY = 'phone_verify';

    public function __construct(private PhoneCodeService $codes) {}

    public function send(Request $request): RedirectResponse
    {
        $phone = Phone::normalize($request->validate(['phone' => ['required', 'string', 'max:30']])['phone'])
            ?? throw ValidationException::withMessages(['phone' => __('Enter the number with the country code, e.g. +91 98000 00001.')]);
        if (User::where('phone', $phone)->whereKeyNot($request->user()->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This number is already on another account.')]);
        }

        $this->codes->send($phone, PhoneCodeService::VERIFY, $request->user(), (string) $request->ip());
        $request->session()->put(self::SESSION_KEY, $phone);

        return to_route('account.settings')->with('status', 'phone-code-sent');
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);
        if (! $phone) {
            return to_route('account.settings');
        }
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];

        $user = $this->codes->check($phone, PhoneCodeService::VERIFY, $code);
        if (! $user->is($request->user())) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Please ask for a new one.')]);
        }
        if (User::where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['code' => __('This number is already on another account.')]);
        }

        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        $request->session()->forget(self::SESSION_KEY);

        return to_route('account.settings')->with('status', 'phone-verified');
    }

    /** Cancels a pending confirmation, or removes the confirmed number. */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->session()->pull(self::SESSION_KEY) === null) {
            $request->user()->forceFill(['phone' => null, 'phone_verified_at' => null])->save();

            return to_route('account.settings')->with('status', 'phone-removed');
        }

        return to_route('account.settings');
    }
}
