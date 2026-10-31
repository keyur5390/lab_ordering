<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\User;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setTimezone('Asia/Kolkata');
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));
    $this->area = ServiceArea::factory()->scheduled(capacity: 2)->create();
    $this->user = User::factory()->create();
    // Friday 30 Oct, 09:00 Kolkata = 27 hours from now.
    $this->order = Order::factory()->for($this->user)->for($this->area)->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))->create();
});

it('lists and shows only the customer\'s own orders', function () {
    Order::factory()->create();

    $this->actingAs($this->user)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('orders/index')->has('orders.data', 1)->where('orders.data.0.number', $this->order->number));

    $this->actingAs($this->user)
        ->get(route('orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/show')
            ->where('order.slot_starts_at', '2026-10-30T09:00:00+05:30')
            ->where('order.status_label', 'Confirmed')
        );

    $this->actingAs(User::factory()->create())->get(route('orders.show', $this->order))->assertForbidden();
});

it('lets customers cancel and reschedule only until the cutoff', function (?int $cutoff, bool $allowed) {
    app(Settings::class)->setCancellationCutoffHours($cutoff);

    $this->actingAs($this->user)
        ->get(route('orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->where('order.can_cancel', $allowed)->where('order.can_reschedule', $allowed));

    $this->actingAs($this->user)->post(route('orders.cancel', $this->order))->assertStatus($allowed ? 302 : 403);

    expect($this->order->fresh()->status)->toBe($allowed ? OrderStatus::Cancelled : OrderStatus::Confirmed);
})->with([
    'no cutoff set: admins only' => [null, false],
    '24 h cutoff, 27 h to go' => [24, true],
    '27 h cutoff, exactly 27 h to go' => [27, true],
    '48 h cutoff, 27 h to go' => [48, false],
]);

it('records when an order was cancelled', function () {
    app(Settings::class)->setCancellationCutoffHours(2);

    $this->actingAs($this->user)->post(route('orders.cancel', $this->order))->assertRedirect(route('orders.show', $this->order));

    expect($this->order->fresh()->cancelled_at)->not->toBeNull();
});

it('reschedules to an available slot', function () {
    app(Settings::class)->setCancellationCutoffHours(2);

    $this->actingAs($this->user)
        ->get(route('orders.reschedule.edit', $this->order))
        ->assertInertia(fn (Assert $page) => $page->component('orders/reschedule')->has('slots', 7)->where('admin', false));

    $this->actingAs($this->user)
        ->put(route('orders.reschedule', $this->order), ['slot_starts_at' => '2026-10-31T10:00:00+05:30'])
        ->assertRedirect(route('orders.show', $this->order));

    expect($this->order->fresh()->slot_starts_at->toIso8601String())->toBe('2026-10-31T04:30:00+00:00')
        ->and($this->order->fresh()->slot_ends_at->toIso8601String())->toBe('2026-10-31T05:30:00+00:00');
});

it('rejects rescheduling to the same, a full or an invalid slot', function () {
    app(Settings::class)->setCancellationCutoffHours(2);
    Order::factory()->count(2)->for($this->area)->at(CarbonImmutable::parse('2026-10-31 04:30', 'UTC'))->create();

    foreach (['2026-10-30T09:00:00+05:30', '2026-10-31T10:00:00+05:30', '2026-10-31T10:30:00+05:30'] as $slot) {
        $this->actingAs($this->user)
            ->put(route('orders.reschedule', $this->order), ['slot_starts_at' => $slot])
            ->assertSessionHasErrors('slot_starts_at');
    }

    expect($this->order->fresh()->slot_starts_at->toIso8601String())->toBe('2026-10-30T03:30:00+00:00');
});

it('does not let customers change orders after collection', function () {
    app(Settings::class)->setCancellationCutoffHours(2);
    $this->order->update(['status' => OrderStatus::SampleCollected]);

    $this->actingAs($this->user)->post(route('orders.cancel', $this->order))->assertForbidden();
});

it('lets admins see, cancel and reschedule any order before collection, whatever the cutoff', function () {
    $admin = User::factory()->admin()->create();
    app(Settings::class)->setCancellationCutoffHours(48);

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['status' => 'confirmed']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/orders/index')->has('orders.data', 1));
    $this->actingAs($admin)
        ->get(route('admin.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->where('order.customer.email', $this->user->email)->where('order.can_cancel', true));

    $this->actingAs($admin)
        ->put(route('admin.orders.reschedule', $this->order), ['slot_starts_at' => '2026-10-31T08:00:00+05:30'])
        ->assertRedirect(route('admin.orders.show', $this->order));
    $this->actingAs($admin)->post(route('admin.orders.cancel', $this->order))->assertRedirect(route('admin.orders.show', $this->order));

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancelled);

    $this->actingAs($admin)->post(route('admin.orders.cancel', $this->order))->assertForbidden();
});

it('forbids customers from the admin order pages', function () {
    $this->actingAs($this->user)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($this->user)->post(route('admin.orders.cancel', $this->order))->assertForbidden();
});

it('saves the cancellation cutoff in settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'cancellation_cutoff_hours' => '12'])
        ->assertSessionHasNoErrors();
    expect(app(Settings::class)->cancellationCutoffHours())->toBe(12);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'cancellation_cutoff_hours' => ''])
        ->assertSessionHasNoErrors();
    expect(app(Settings::class)->cancellationCutoffHours())->toBeNull();
});
