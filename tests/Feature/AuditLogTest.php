<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
});

it('records staff changes with who, what, the subject and the outcome', function () {
    $user = User::factory()->create(['email' => 'pat@example.com']);

    $this->actingAs($this->admin)->post(route('admin.users.disable', $user))->assertRedirect();

    $entry = AuditLog::sole();
    expect($entry->user_id)->toBe($this->admin->id)
        ->and($entry->user_name)->toBe('Ada Admin')
        ->and($entry->role)->toBe('admin')
        ->and($entry->action)->toBe('admin.users.disable')
        ->and($entry->subject)->toBe('pat@example.com')
        ->and($entry->outcome)->toBe('done')
        ->and($entry->method)->toBe('POST')
        ->and($entry->path)->toBe("/admin/users/{$user->id}/disable");
});

it('does not record page views or customers\' own actions', function () {
    $customer = User::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($customer)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ]);

    expect(AuditLog::count())->toBe(0);
});

it('never stores passwords and stores uploaded files by name', function () {
    Storage::fake('local');
    $this->actingAs($this->admin)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ]);
    expect(AuditLog::sole()->input)->toBeNull();

    $order = Order::factory()->status(OrderStatus::ReceivedAtLab)->create();
    $item = $order->items()->create([
        'patient_name' => 'Asha Rao', 'patient_date_of_birth' => '1980-01-01', 'patient_sex' => 'female',
        'item_type' => 'test', 'name' => 'CBC', 'code' => 'CBC', 'price_amount' => 100,
    ]);
    $this->actingAs($this->admin)->post(route('admin.lab.reports.store', [$order, $item]), [
        'report' => UploadedFile::fake()->create('cbc.pdf', 10, 'application/pdf'),
    ]);

    $upload = AuditLog::where('action', 'admin.lab.reports.store')->sole();
    expect($upload->input)->toEqual(['report' => 'file: cbc.pdf'])
        ->and($upload->subject)->toContain($order->number);
});

it('records rejected and forbidden attempts', function () {
    $lab = User::factory()->create(['role' => UserRole::LabStaff]);
    $order = Order::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.users.role', User::factory()->create()), ['role' => 'emperor'])->assertSessionHasErrors('role');
    $this->actingAs($lab)->post(route('admin.orders.cancel', $order))->assertForbidden();

    expect(AuditLog::orderBy('id')->pluck('outcome')->all())->toBe(['rejected', 'forbidden'])
        ->and(AuditLog::latest('id')->first()->role)->toBe('lab_staff');
});

it('records staff sign-ins, sign-outs and failed sign-ins, but not customers\'', function () {
    $customer = User::factory()->create();

    $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'password']);

    expect(AuditLog::orderBy('id')->get()->map(fn (AuditLog $entry) => [$entry->action, $entry->outcome, $entry->user_id])->all())
        ->toBe([
            ['auth.failed', 'rejected', $this->admin->id],
            ['auth.login', 'done', $this->admin->id],
            ['auth.logout', 'done', $this->admin->id],
        ]);
});

it('shows the log to admins only, with filters', function () {
    $other = User::factory()->admin()->create(['name' => 'Bo Admin']);
    $target = User::factory()->create();
    $this->actingAs($this->admin)->post(route('admin.users.disable', $target));
    $this->actingAs($other)->post(route('admin.users.enable', $target));
    AuditLog::latest('id')->first()->update(['created_at' => now()->subDays(3)]);

    $this->actingAs($this->admin)->get(route('admin.audit-log'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/audit-log')
            ->has('entries.data', 2)->has('entries.meta')
            ->where('entries.data.0.label', 'Disabled an account')
            ->where('entries.data.0.who', 'Ada Admin')
            ->where('entries.data.0.role', 'Admin')
            ->where('actions', [
                ['value' => 'admin.users.disable', 'label' => 'Disabled an account'],
                ['value' => 'admin.users.enable', 'label' => 'Enabled an account'],
            ]));
    $this->actingAs($this->admin)->get(route('admin.audit-log', ['user' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.who', 'Bo Admin'));
    $this->actingAs($this->admin)->get(route('admin.audit-log', ['action' => 'admin.users.disable']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    $this->actingAs($this->admin)->get(route('admin.audit-log', ['from' => now()->subDay()->format('Y-m-d')]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.who', 'Ada Admin'));
    $this->actingAs($this->admin)->get(route('admin.audit-log', ['outcome' => 'forbidden']))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));

    $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))->get(route('admin.audit-log'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.audit-log'))->assertForbidden();
});

it('labels actions it has no name for from the route', function () {
    expect(AuditLogger::label('admin.coupons.update'))->toBe('Updated coupons')
        ->and(AuditLogger::label('admin.service-areas.destroy'))->toBe('Deleted service areas');
});
