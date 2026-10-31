<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Disabled accounts can't sign in (client, 2026-10-31).
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();
            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }
            if ($user->isDisabled()) {
                throw ValidationException::withMessages([Fortify::username() => 'This account has been disabled. Please contact us.']);
            }

            return $user;
        });

        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Render each Fortify screen as an Inertia page.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password'));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->value(),
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('auth/verify-email'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
