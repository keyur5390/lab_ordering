<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the forgot password page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
});

it('emails a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset password page with the token and email', function () {
    $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'asha@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/reset-password')
            ->where('token', 'abc123')
            ->where('email', 'asha@example.com')
        );
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
