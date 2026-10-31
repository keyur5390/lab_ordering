<?php

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $this->customer = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
});

it('lists users with search and filters', function () {
    User::factory()->create(['name' => 'Lab Lee', 'role' => UserRole::LabStaff]);
    User::factory()->create(['name' => 'Off Owen', 'disabled_at' => now()]);
    Order::factory()->for($this->customer)->count(2)->create();

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')->has('users.data', 4)->has('users.meta')
            ->where('users.data.1.name', 'Asha Rao')->where('users.data.1.orders_count', 2));

    $names = fn (array $query) => collect($this->actingAs($this->admin)->get(route('admin.users.index', $query))->viewData('page')['props']['users']['data'])->pluck('name')->all();
    expect($names(['q' => 'ASHA@']))->toBe(['Asha Rao'])
        ->and($names(['role' => 'lab_staff']))->toBe(['Lab Lee'])
        ->and($names(['status' => 'disabled']))->toBe(['Off Owen'])
        ->and($names(['q' => '%']))->toBe([]);
});

it('shows a customer\'s patients, addresses and orders', function () {
    $this->customer->patients()->sole()->update(['phone' => '+91 98123 45678']);
    Address::factory()->for($this->customer)->create(['line1' => '4 FC Road']);
    $order = Order::factory()->for($this->customer)->create();

    $this->actingAs($this->admin)->get(route('admin.users.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/show')
            ->where('user.email', 'asha@example.com')->where('user.is_self', false)
            ->has('patients', 1)->where('patients.0.phone', '+91 98123 45678')
            ->where('addresses.0.line1', '4 FC Road')
            ->where('orders.0.number', $order->number));
});

it('changes roles, but never your own', function () {
    $this->actingAs($this->admin)->put(route('admin.users.role', $this->customer), ['role' => 'collector'])
        ->assertRedirect(route('admin.users.show', $this->customer));
    expect($this->customer->fresh()->role)->toBe(UserRole::Collector);

    $this->actingAs($this->admin)->put(route('admin.users.role', $this->customer), ['role' => 'king'])->assertSessionHasErrors('role');
    $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), ['role' => 'customer'])->assertSessionHasErrors('role');
    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

it('disables accounts: no sign-in, open sessions end, history stays', function () {
    $order = Order::factory()->for($this->customer)->create();
    $this->actingAs($this->customer)->get(route('account'))->assertOk();

    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->customer))->assertSessionHasNoErrors();
    expect($this->customer->fresh()->isDisabled())->toBeTrue();

    // A session that was already open is closed on the next request.
    $this->actingAs($this->customer->fresh())->get(route('account'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'asha@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been disabled. Please contact us.']);
    $this->assertGuest();
    expect($order->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->post(route('admin.users.enable', $this->customer));
    auth()->guard('web')->logout();
    $this->post(route('login.store'), ['email' => 'asha@example.com', 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($this->customer);
});

it('won\'t let admins disable themselves', function () {
    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->admin))->assertSessionHasErrors('account');
    expect($this->admin->fresh()->isDisabled())->toBeFalse();
});

it('invites staff, who set their password from the email', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'Meera Iyer', 'email' => 'Meera@Lab.test', 'role' => 'lab_staff'])
        ->assertRedirect(route('admin.users.show', User::where('email', 'meera@lab.test')->sole()));

    $meera = User::where('email', 'meera@lab.test')->sole();
    expect($meera->role)->toBe(UserRole::LabStaff)->and($meera->hasVerifiedEmail())->toBeTrue();

    $token = null;
    Notification::assertSentTo($meera, StaffInvitation::class, function (StaffInvitation $invitation) use ($meera, &$token) {
        $mail = $invitation->toMail($meera);
        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($mail->actionUrl, PHP_URL_PATH));

        return $mail->subject === 'You\'ve been invited to '.config('app.name') && $query['email'] === 'meera@lab.test';
    });

    auth()->guard('web')->logout();
    $this->post(route('password.update'), ['token' => $token, 'email' => 'meera@lab.test', 'password' => 'a-new-Passw0rd!', 'password_confirmation' => 'a-new-Passw0rd!'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('a-new-Passw0rd!', $meera->fresh()->password))->toBeTrue();
});

it('validates invitations', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'asha@example.com', 'role' => 'customer'])
        ->assertSessionHasErrors(['email', 'role']);
    $this->actingAs($this->admin)->get(route('admin.users.create'))
        ->assertInertia(fn (Assert $page) => $page->has('roles', 3)->where('roles.0.value', 'admin'));
});

it('is admin-only', function () {
    foreach ([$this->customer, User::factory()->create(['role' => UserRole::LabStaff])] as $user) {
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.disable', $this->admin))->assertForbidden();
    }
});
