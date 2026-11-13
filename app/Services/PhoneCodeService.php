<?php

namespace App\Services;

use App\Models\PhoneCode;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes by SMS (B-30): to sign in with a phone, and to verify a number added to an account.
 * TODO(client): confirm the code lifetime, tries and sending limits.
 */
class PhoneCodeService
{
    public const LOGIN = 'login';

    public const VERIFY = 'verify';

    /** [10] minutes. */
    public const TTL_MINUTES = 10;

    /** [5] wrong tries before the code stops working. */
    public const MAX_ATTEMPTS = 5;

    /** [3] texts per number, and [10] per IP address, per [10] minutes. */
    public const SENDS_PER_PHONE = 3;

    public const SENDS_PER_IP = 10;

    public const SEND_WINDOW_SECONDS = 600;

    public function __construct(private Settings $settings) {}

    /** Phone codes need an SMS driver (Admin → Settings → Notifications). */
    public function available(): bool
    {
        return $this->settings->smsDriver() !== null;
    }

    /**
     * Texts a new code for the purpose, replacing any earlier one. With no account (a sign-in for a
     * number nobody has), nothing is sent but the limits still count, so both cases look the same.
     *
     * @throws ValidationException when sending too often, or SMS is off
     */
    public function send(string $phone, string $purpose, ?User $user, string $ip, string $field = 'phone'): void
    {
        $driver = $this->settings->smsDriver();
        if (! $driver) {
            throw ValidationException::withMessages([$field => __('Text messages are switched off. Please use your email instead.')]);
        }
        $this->throttle(["phone-code:{$phone}" => self::SENDS_PER_PHONE, "phone-code-ip:{$ip}" => self::SENDS_PER_IP], $field);
        if (! $user) {
            return;
        }

        $code = (string) random_int(100000, 999999);
        PhoneCode::where('phone', $phone)->where('purpose', $purpose)->delete();
        PhoneCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $values = ['site' => config('app.name'), 'code' => $code, 'minutes' => self::TTL_MINUTES];
        $driver->sender()->send($phone, $purpose === self::LOGIN
            ? __(':site: your sign-in code is :code. It expires in :minutes minutes. Never share it.', $values)
            : __(':site: your code to confirm this number is :code. It expires in :minutes minutes.', $values));
    }

    /**
     * The account the code was sent for, once the code is right. The code then stops working.
     *
     * @throws ValidationException
     */
    public function check(string $phone, string $purpose, string $code, string $field = 'code'): User
    {
        $record = PhoneCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();
        $wrong = fn () => ValidationException::withMessages([$field => __('That code is wrong or has expired. Please ask for a new one.')]);

        if (! $record || $record->expires_at->isPast() || $record->attempts >= self::MAX_ATTEMPTS) {
            throw $wrong();
        }
        if (! Hash::check($code, $record->code)) {
            $record->increment('attempts');
            throw $wrong();
        }

        $record->delete();

        return $record->user;
    }

    /**
     * @param  array<string, int>  $limits  key => sends allowed per window
     *
     * @throws ValidationException
     */
    private function throttle(array $limits, string $field): void
    {
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                throw ValidationException::withMessages([$field => trans_choice(
                    'Too many codes sent. Please try again in :count minute.|Too many codes sent. Please try again in :count minutes.',
                    $minutes,
                )]);
            }
        }
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, self::SEND_WINDOW_SECONDS);
        }
    }
}
