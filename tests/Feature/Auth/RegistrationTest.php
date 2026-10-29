<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the registration page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

it('registers a user, logs them in and sends a verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Asha Patel',
        'email' => 'Asha@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('account'));

    $user = User::sole();

    expect($user->email)->toBe('asha@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects invalid registration data', function (array $data, string $field) {
    $this->post(route('register.store'), [
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$data,
    ])->assertSessionHasErrors($field);

    $this->assertGuest();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different'], 'password'],
]);

it('rejects an email that is already registered, regardless of case', function () {
    User::factory()->create(['email' => 'asha@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Asha Patel',
        'email' => 'ASHA@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
});

it('redirects signed-in users away from the registration page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(route('account'));
});
