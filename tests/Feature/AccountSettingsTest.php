<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->user = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']));

it('shows the settings page to signed-in users, verified or not', function () {
    $this->get(route('account.settings'))->assertRedirect(route('login'));

    $this->actingAs($this->user)->get(route('account.settings'))->assertInertia(fn (Assert $page) => $page->component('account-settings'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('account.settings'))->assertOk();
});

it('changes the name without touching verification', function () {
    $this->actingAs($this->user)->put(route('user-profile-information.update'), ['name' => 'Asha R.', 'email' => 'asha@example.com'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-information-updated');

    expect($this->user->fresh()->name)->toBe('Asha R.')->and($this->user->fresh()->hasVerifiedEmail())->toBeTrue();
    // The "Self" patient profile keeps its own name.
    expect($this->user->patients()->sole()->name)->toBe('Asha Rao');
});

it('asks for the new email to be confirmed', function () {
    Notification::fake();

    $this->actingAs($this->user)->put(route('user-profile-information.update'), ['name' => 'Asha Rao', 'email' => ' New@Example.com '])
        ->assertSessionHasNoErrors();

    $user = $this->user->fresh();
    expect($user->email)->toBe('new@example.com')->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);

    // Until it's confirmed, ordering pages ask for verification, but settings stay open to fix a typo.
    $this->actingAs($user)->get(route('orders.index'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('account.settings'))->assertOk();
});

it('rejects a taken email or a missing name', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)->put(route('user-profile-information.update'), ['name' => '', 'email' => 'TAKEN@example.com'])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['name', 'email']);
    expect($this->user->fresh()->email)->toBe('asha@example.com');
});

it('changes the password only with the current one', function () {
    $this->actingAs($this->user)->put(route('user-password.update'), [
        'current_password' => 'wrong', 'password' => 'n3w-Passw0rd!', 'password_confirmation' => 'n3w-Passw0rd!',
    ])->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'That is not your current password.']);

    $this->actingAs($this->user)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'n3w-Passw0rd!', 'password_confirmation' => 'different',
    ])->assertSessionHasErrorsIn('updatePassword', ['password']);

    $this->actingAs($this->user)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'n3w-Passw0rd!', 'password_confirmation' => 'n3w-Passw0rd!',
    ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

    expect(Hash::check('n3w-Passw0rd!', $this->user->fresh()->password))->toBeTrue();
});

it('keeps guests out of the update routes', function () {
    $this->put(route('user-profile-information.update'), ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect(route('login'));
    $this->put(route('user-password.update'), [])->assertRedirect(route('login'));
});
