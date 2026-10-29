<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the confirm password page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/confirm-password'));
});

it('confirms the password', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('rejects a wrong password', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');
});
