<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('forbids customers', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('sends unverified admins to the verification notice', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('shows the dashboard to admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
});

it('shares role and admin access with the frontend', function (User $user, string $role, bool $canAccessAdmin) {
    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', $role)
            ->where('auth.user.can_access_admin', $canAccessAdmin)
        );
})->with([
    'customer' => [fn () => User::factory()->create(), 'customer', false],
    'admin' => [fn () => User::factory()->admin()->create(), 'admin', true],
]);
