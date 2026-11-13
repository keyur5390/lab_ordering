<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhoneCodeService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sign in with a verified mobile number and a code by SMS (B-30), alongside email and password.
 * Responses never say whether a number belongs to an account.
 */
class PhoneLoginController extends Controller
{
    /** Session key: the number a code was sent to. */
    public const SESSION_KEY = 'phone_login';

    public function __construct(private PhoneCodeService $codes) {}

    public function send(Request $request): RedirectResponse
    {
        $phone = $this->phone($request);
        $user = User::where('phone', $phone)->whereNotNull('phone_verified_at')
            ->whereNull('disabled_at')->whereNull('anonymised_at')->first();

        $this->codes->send($phone, PhoneCodeService::LOGIN, $user, (string) $request->ip());
        $request->session()->put(self::SESSION_KEY, $phone);

        return to_route('login')->with('status', __('If :phone is on an account, we\'ve texted it a code.', ['phone' => Phone::mask($phone)]));
    }

    public function verify(Request $request): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);
        if (! $phone) {
            return to_route('login');
        }
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];

        $user = $this->codes->check($phone, PhoneCodeService::LOGIN, $code);
        if ($user->isDisabled() || $user->phone !== $phone) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Please ask for a new one.')]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /** "Use a different number". */
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('login');
    }

    private function phone(Request $request): string
    {
        $phone = Phone::normalize($request->validate(['phone' => ['required', 'string', 'max:30']])['phone']);

        return $phone ?? throw ValidationException::withMessages(['phone' => __('Enter your mobile number with the country code, e.g. +91 98000 00001.')]);
    }
}
