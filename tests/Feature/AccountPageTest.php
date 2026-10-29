<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to the login page', function () {
    $this->get(route('account'))->assertRedirect(route('login'));
});

it('sends unverified users to the verification notice', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('account'))
        ->assertRedirect(route('verification.notice'));
});

it('shows the account page to verified users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('account'));
});
