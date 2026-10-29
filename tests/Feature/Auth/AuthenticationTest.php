<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canResetPassword', true)
        );
});

it('logs a user in with valid credentials', function () {
    $user = User::factory()->create(['email' => 'asha@example.com']);

    $this->post(route('login.store'), [
        'email' => 'Asha@Example.com',
        'password' => 'password',
    ])->assertRedirect(route('account'));

    $this->assertAuthenticatedAs($user);
});

it('does not log a user in with a wrong password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
    RateLimiter::clear('login');
});

it('logs a user out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('shares the signed-in user with every page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => true,
                'role' => 'customer',
                'can_access_admin' => false,
            ])
        );
});

it('shares a null user with guests', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
