<?php

use App\Enums\UserRole;
use App\Models\User;

it('promotes a user to admin', function () {
    $user = User::factory()->create(['email' => 'asha@example.com']);

    $this->artisan('user:role', ['email' => 'Asha@Example.com', 'role' => 'admin'])
        ->expectsOutput('asha@example.com is now Admin.')
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

it('demotes an admin to customer', function () {
    $user = User::factory()->admin()->create();

    $this->artisan('user:role', ['email' => $user->email, 'role' => 'customer'])->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Customer);
});

it('rejects an unknown role', function () {
    $user = User::factory()->create();

    $this->artisan('user:role', ['email' => $user->email, 'role' => 'superuser'])
        ->expectsOutput('Unknown role. Use one of: customer, admin, lab_staff, collector.')
        ->assertFailed();

    expect($user->fresh()->role)->toBe(UserRole::Customer);
});

it('rejects an unknown email', function () {
    $this->artisan('user:role', ['email' => 'nobody@example.com', 'role' => 'admin'])
        ->expectsOutput('No user with that email.')
        ->assertFailed();
});
