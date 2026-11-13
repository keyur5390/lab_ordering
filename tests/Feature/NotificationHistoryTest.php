<?php

use App\Enums\Currency;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Enums\SmsDriver;
use App\Enums\UserRole;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\OrderNotifier;
use App\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.twilio.sid' => 'AC1', 'services.twilio.token' => 't', 'services.twilio.from' => '+15005550006']);
    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setAdminNotificationEmail('ops@example.com');
    $this->settings->setSmsDriver(SmsDriver::Twilio);

    $this->customer = User::factory()->create(['email' => 'asha@example.com']);
    $this->customer->patients()->sole()->update(['phone' => '+91 98123 45678']);
    $this->order = Order::factory()->for($this->customer)->create();
    $this->admin = User::factory()->admin()->create();
});

it('logs each message on each channel when it is sent', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    app(OrderNotifier::class)->placed($this->order);

    $logs = NotificationLog::orderBy('id')->get();
    expect($logs->map(fn ($log) => [$log->event, $log->channel, $log->recipient, $log->status])->all())->toBe([
        [NotificationEvent::OrderConfirmed, NotificationChannel::Mail, 'asha@example.com', 'sent'],
        [NotificationEvent::OrderConfirmed, NotificationChannel::Sms, '+919812345678', 'sent'],
        [NotificationEvent::AdminNewOrder, NotificationChannel::Mail, 'ops@example.com', 'sent'],
    ])->and($logs->pluck('order_id')->unique()->all())->toBe([$this->order->id]);
});

it('logs a failed channel with the error, without blocking the others', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Unreachable'], 503)]);

    try {
        $this->customer->notify(new OrderNotification(NotificationEvent::OrderConfirmed, $this->order));
    } catch (Throwable) {
        // The sync queue re-throws; a worker would retry and then mark the job failed.
    }

    $failed = NotificationLog::where('status', NotificationLog::FAILED)->sole();
    expect($failed->channel)->toBe(NotificationChannel::Sms)
        ->and($failed->error)->toContain('Twilio request failed')
        ->and(NotificationLog::where('status', NotificationLog::SENT)->where('channel', NotificationChannel::Mail)->count())->toBe(1);
});

it('lists the history with filters for admins', function () {
    NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::OrderConfirmed, 'channel' => NotificationChannel::Mail, 'recipient' => 'asha@example.com', 'status' => 'sent']);
    NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::OrderCancelled, 'channel' => NotificationChannel::Sms, 'recipient' => '+919812345678', 'status' => 'failed', 'error' => 'Unreachable']);

    $this->actingAs($this->admin)->get(route('admin.notifications.history'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/history')->has('logs.data', 2)->has('logs.meta')
            ->where('logs.data.0.event_label', 'Order cancelled')->where('logs.data.0.order_number', $this->order->number));
    $this->actingAs($this->admin)->get(route('admin.notifications.history', ['status' => 'failed', 'channel' => 'sms']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.error', 'Unreachable'));
    $this->actingAs($this->admin)->get(route('admin.notifications.history', ['order' => strtolower($this->order->number)]))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2));

    $this->actingAs($this->admin)->get(route('admin.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->has('messages', 2));
    $alerts = $this->actingAs($this->admin)->get(route('admin.dashboard'))->viewData('page')['props']['alerts'];
    expect(collect($alerts)->pluck('message'))->toContain('1 message failed to send in the last 24 hours.');
});

it('resends a message on its channel only', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM2'], 201)]);
    $log = NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::OrderConfirmed, 'channel' => NotificationChannel::Sms, 'recipient' => '+919812345678', 'status' => 'failed']);

    $this->actingAs($this->admin)->post(route('admin.notifications.resend', $log))->assertSessionHas('status', 'SMS resent to +919812345678.');

    Http::assertSentCount(1);
    expect(NotificationLog::where('status', 'sent')->get()->map(fn ($l) => $l->channel)->all())->toBe([NotificationChannel::Sms]);
});

it('resends admin alerts to the address they went to', function () {
    $log = NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::AdminNewOrder, 'channel' => NotificationChannel::Mail, 'recipient' => 'old-ops@example.com', 'status' => 'failed']);

    $this->actingAs($this->admin)->post(route('admin.notifications.resend', $log));

    expect(NotificationLog::where('status', 'sent')->sole()->recipient)->toBe('old-ops@example.com');
});

it('is admin-only', function () {
    $log = NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::OrderConfirmed, 'channel' => NotificationChannel::Mail, 'recipient' => 'a@b.c', 'status' => 'sent']);

    foreach ([$this->customer, User::factory()->create(['role' => UserRole::LabStaff])] as $user) {
        $this->actingAs($user)->get(route('admin.notifications.history'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.notifications.resend', $log))->assertForbidden();
    }
    // Lab staff see the order page without the messages.
    $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))->get(route('admin.orders.show', $this->order))
        ->assertInertia(fn (Assert $page) => $page->where('messages', []));
});
