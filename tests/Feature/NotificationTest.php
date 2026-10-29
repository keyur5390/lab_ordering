<?php

use App\Enums\Currency;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PatientRelationship;
use App\Enums\PaymentGatewayName;
use App\Enums\PaymentMethod;
use App\Enums\SmsDriver;
use App\Models\Address;
use App\Models\Cart;
use App\Models\LabTest;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\OrderNotification;
use App\Services\OrderNotifier;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ServiceAreaService;
use App\Services\Sms\SmsException;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.webhook_secret' => 'whsec_test',
        'services.twilio.sid' => 'AC123',
        'services.twilio.token' => 'twilio_token',
        'services.twilio.from' => '+15005550006',
    ]);

    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setTimezone('Asia/Kolkata');
    $this->settings->setCancellationCutoffHours(2);
    $this->settings->setAdminNotificationEmail('ops@example.com');
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->user = User::factory()->create(['name' => 'Asha Rao']);
    $this->user->patients()->sole()->update(['date_of_birth' => '1990-01-01', 'sex' => 'female', 'phone' => '+91 98123 45678']);
    $this->area = ServiceArea::factory()->scheduled(capacity: 2)->create(['country' => 'IN']);
    // Friday 30 Oct, 09:00–10:00 Kolkata, ₹500.
    $this->order = Order::factory()->for($this->user)->for($this->area)
        ->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))
        ->create(['address' => ['line1' => '4 FC Road', 'city' => 'Pune', 'postcode' => '411004', 'country' => 'IN'], 'total_amount' => 50000]);
});

/** Places an order through checkout, as a customer would. */
function notifPlace(string $paymentMethod): Order
{
    app(ServiceAreaService::class)->syncPostcodes(test()->area, ['411004']);
    $address = Address::factory()->for(test()->user)->create(['postcode' => '411004', 'country' => 'IN']);
    $item = Cart::create(['user_id' => test()->user->id])->items()->create(['lab_test_id' => LabTest::factory()->create()->id]);

    return app(OrderService::class)->place(test()->user, Cart::where('user_id', test()->user->id)->sole(), [
        $item->id => [test()->user->patients()->sole()->id],
    ], $address, CarbonImmutable::parse('2026-10-31T09:00:00+05:30'), null, PaymentMethod::from($paymentMethod));
}

function sentEvents(object $notifiable): array
{
    return Notification::sent($notifiable, OrderNotification::class)->map(fn (OrderNotification $n) => $n->event)->all();
}

describe('who is told what', function () {
    beforeEach(fn () => Notification::fake());

    it('confirms pay-at-collection orders to the customer and alerts admins', function () {
        $order = notifPlace('pay_at_collection');

        Notification::assertSentTo($this->user, OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::OrderConfirmed && $n->order->is($order));
        Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n, array $channels, AnonymousNotifiable $notifiable) => $n->event === NotificationEvent::AdminNewOrder
            && $notifiable->routes['mail'] === 'ops@example.com');
    });

    it('sends nothing for unpaid online orders until the payment arrives', function () {
        $this->settings->setPaymentGateway(PaymentGatewayName::Stripe);
        $order = notifPlace('online');
        Notification::assertNothingSent();

        $payment = Payment::factory()->for($order)->create(['amount' => $order->total_amount]);
        app(PaymentService::class)->confirm($payment, 'pi_1');

        expect(sentEvents($this->user))->toBe([NotificationEvent::PaymentReceived]);
        Notification::assertSentTo($this->user, OrderNotification::class, fn (OrderNotification $n) => $n->amount === $order->total_amount);
        Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::AdminNewOrder);
    });

    it('skips admin alerts when no admin address is set', function () {
        $this->settings->setAdminNotificationEmail(null);
        notifPlace('pay_at_collection');

        Notification::assertSentOnDemandTimes(OrderNotification::class, 0);
    });

    it('tells the customer about cancellation and refund, and admins about the cancellation', function () {
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
        $this->order->update(['payment_method' => PaymentMethod::Online]);
        Payment::factory()->paid()->for($this->order)->create(['amount' => 50000]);

        $this->actingAs($this->user)->post(route('orders.cancel', $this->order));

        expect(sentEvents($this->user))->toBe([NotificationEvent::OrderCancelled, NotificationEvent::RefundIssued]);
        Notification::assertSentTo($this->user, OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::RefundIssued && $n->amount === 50000);
        Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::AdminOrderCancelled);
    });

    it('alerts admins, not the customer, when a refund fails', function () {
        Http::fake(['api.stripe.com/*' => Http::response([], 500)]);
        $this->order->update(['payment_method' => PaymentMethod::Online]);
        Payment::factory()->paid()->for($this->order)->create(['amount' => 50000]);

        app(PaymentService::class)->refund($this->order, 20000);

        expect(sentEvents($this->user))->toBe([]);
        Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::AdminRefundFailed && $n->amount === 20000);
    });

    it('tells customers when an unpaid order expires', function () {
        $this->order->update(['status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Online, 'payment_expires_at' => now()->subMinute()]);

        expect(app(PaymentService::class)->expireUnpaid())->toBe(1);

        expect(sentEvents($this->user))->toBe([NotificationEvent::PaymentExpired]);
        Notification::assertSentOnDemandTimes(OrderNotification::class, 0);
    });

    it('tells customers about a new slot and re-arms the reminder', function () {
        $this->order->update(['reminder_sent_at' => now()]);

        $this->actingAs($this->user)->put(route('orders.reschedule', $this->order), ['slot_starts_at' => '2026-10-31T10:00:00+05:30']);

        expect(sentEvents($this->user))->toBe([NotificationEvent::OrderRescheduled])
            ->and($this->order->fresh()->reminder_sent_at)->toBeNull();
    });
});

describe('reminders', function () {
    beforeEach(function () {
        Notification::fake();
        // Booked well before the reminder window.
        $this->order->forceFill(['created_at' => now()->subDays(3)])->save();
    });

    it('reminds once, the set number of hours before the slot', function () {
        // The slot is 27 h away: outside a 24 h window, inside a 30 h one.
        $this->artisan('orders:send-reminders')->expectsOutput('Sent 0 reminders.');

        $this->settings->setReminderHoursBefore(30);
        $this->artisan('orders:send-reminders')->expectsOutput('Sent 1 reminder.');
        $this->artisan('orders:send-reminders')->expectsOutput('Sent 0 reminders.');

        expect(sentEvents($this->user))->toBe([NotificationEvent::CollectionReminder])
            ->and($this->order->fresh()->reminder_sent_at)->not->toBeNull();
    });

    it('skips cancelled orders, orders booked inside the window, and when reminders are off', function () {
        $this->settings->setReminderHoursBefore(30);
        $this->order->update(['status' => OrderStatus::Cancelled]);
        Order::factory()->for($this->area)->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))->create();
        expect(app(OrderNotifier::class)->sendDueReminders())->toBe(0);

        $this->order->update(['status' => OrderStatus::Confirmed]);
        $this->settings->setReminderHoursBefore(0);
        expect(app(OrderNotifier::class)->sendDueReminders())->toBe(0);
        Notification::assertNothingSent();
    });
});

describe('channels and wording', function () {
    it('fills the admin-edited wording with the order details', function () {
        NotificationTemplate::where('event', 'order_confirmed')->where('channel', 'mail')->sole()
            ->update(['subject' => 'Order {order_number} for {customer_name}', 'body' => "On {slot} at {address}.\n\nTotal {total}. {unknown}\n\nThanks, {customer_name}"]);
        $this->user->update(['name' => 'Asha <b>Rao</b>']);

        $mail = (new OrderNotification(NotificationEvent::OrderConfirmed, $this->order->fresh()))->toMail($this->user);

        expect($mail->subject)->toBe("Order {$this->order->number} for Asha <b>Rao</b>")
            ->and($mail->introLines)->toBe(['On Fri 30 Oct, 09:00–10:00 at 4 FC Road, Pune, 411004.', 'Total ₹500.00. {unknown}', 'Thanks, Asha <b>Rao</b>'])
            ->and($mail->actionUrl)->toBe(route('orders.show', $this->order));
        // Customer-entered text is escaped in the email.
        expect((string) $mail->render())->toContain('Asha &lt;b&gt;Rao&lt;/b&gt;')->not->toContain('<b>Rao</b>');
    });

    it('picks channels from the settings, the wording switches and the customer', function (Closure $setup, array $expected) {
        $setup();
        $notification = new OrderNotification(NotificationEvent::OrderConfirmed, $this->order);

        expect($notification->via($this->user->fresh()))->toBe($expected);
    })->with([
        'email only by default' => [fn () => null, ['mail']],
        'SMS switched on' => [fn () => test()->settings->setSmsDriver(SmsDriver::Twilio), ['mail', SmsChannel::class]],
        'email switched off' => [fn () => test()->settings->setNotifyByEmail(false), []],
        'email wording off' => [fn () => NotificationTemplate::where('event', 'order_confirmed')->where('channel', 'mail')->update(['is_active' => false]), []],
        'customer opted out of SMS' => [function () {
            test()->settings->setSmsDriver(SmsDriver::Twilio);
            test()->user->forceFill(['sms_opt_out' => true])->save();
        }, ['mail']],
        'phone without country code' => [function () {
            test()->settings->setSmsDriver(SmsDriver::Twilio);
            test()->user->patients()->sole()->update(['phone' => '98123 45678']);
        }, ['mail']],
        'Twilio keys removed' => [function () {
            test()->settings->setSmsDriver(SmsDriver::Twilio);
            config(['services.twilio.token' => null]);
        }, ['mail']],
    ]);

    it('emails admin alerts even when customer emails are off', function () {
        $this->settings->setNotifyByEmail(false);

        expect((new OrderNotification(NotificationEvent::AdminNewOrder, $this->order))->via(new AnonymousNotifiable))->toBe(['mail'])
            ->and((new OrderNotification(NotificationEvent::AdminNewOrder, $this->order))->toMail(new AnonymousNotifiable)->actionUrl)
            ->toBe(route('admin.orders.show', $this->order));
    });

    it('sends SMS through Twilio to the Self profile phone', function () {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->settings->setSmsDriver(SmsDriver::Twilio);

        $this->user->notify(new OrderNotification(NotificationEvent::OrderConfirmed, $this->order));

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $request['To'] === '+919812345678'
            && $request['From'] === '+15005550006'
            && $request['Body'] === config('app.name').": order {$this->order->number} confirmed. Collection Fri 30 Oct, 09:00–10:00. Pay ₹500.00 at collection."
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC123:twilio_token')));
    });

    it('fails the job so it is retried when the SMS provider is down', function () {
        Http::fake(['api.twilio.com/*' => Http::response([], 503)]);
        $this->settings->setSmsDriver(SmsDriver::Twilio);
        $this->settings->setNotifyByEmail(false);

        $this->user->notify(new OrderNotification(NotificationEvent::OrderConfirmed, $this->order));
    })->throws(SmsException::class);

    it('only sends SMS to the Self profile', function () {
        $this->user->patients()->sole()->update(['phone' => null]);
        $this->user->patients()->create(['name' => 'Parent', 'relationship' => PatientRelationship::Parent, 'phone' => '+919800000000']);

        expect($this->user->routeNotificationForSms())->toBeNull();
    });
});

describe('admin wording', function () {
    beforeEach(fn () => $this->admin = User::factory()->admin()->create());

    it('lists every event with its channels', function () {
        $this->actingAs($this->admin)->get(route('admin.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/index')
                ->has('events', count(NotificationEvent::cases()))
                ->has('events.0.templates', 2)
                ->where('events.7.value', 'admin_new_order')
                ->has('events.7.templates', 1));
    });

    it('edits the wording, rejecting unknown placeholders', function () {
        $template = NotificationTemplate::where('event', 'refund_issued')->where('channel', 'mail')->sole();

        $this->actingAs($this->admin)->get(route('admin.notifications.edit', $template))
            ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/edit')->has('placeholders.amount'));

        $this->actingAs($this->admin)->put(route('admin.notifications.update', $template), ['subject' => 'Hi {nickname}', 'body' => 'x', 'is_active' => true])
            ->assertSessionHasErrors('subject');

        $this->actingAs($this->admin)->put(route('admin.notifications.update', $template), ['subject' => 'Refund {amount}', 'body' => 'Sent {amount}.', 'is_active' => false])
            ->assertRedirect(route('admin.notifications.index'));

        expect($template->fresh()->only(['subject', 'body', 'is_active']))->toBe(['subject' => 'Refund {amount}', 'body' => 'Sent {amount}.', 'is_active' => false]);
    });

    it('keeps SMS wording short and without a subject', function () {
        $template = NotificationTemplate::where('event', 'order_cancelled')->where('channel', NotificationChannel::Sms)->sole();

        $this->actingAs($this->admin)->put(route('admin.notifications.update', $template), ['subject' => 'x', 'body' => str_repeat('a', 641), 'is_active' => true])
            ->assertSessionHasErrors(['subject', 'body']);
    });

    it('is for admins only', function () {
        $template = NotificationTemplate::first();

        $this->actingAs($this->user)->get(route('admin.notifications.index'))->assertForbidden();
        $this->actingAs($this->user)->put(route('admin.notifications.update', $template), ['body' => 'x'])->assertForbidden();
    });

    it('saves the notification settings, allowing only SMS drivers whose keys are set', function () {
        $save = fn (array $data) => $this->actingAs($this->admin)->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', ...$data]);

        $save(['notify_by_email' => false, 'sms_driver' => 'twilio', 'reminder_hours_before' => '12', 'admin_notification_email' => 'Alerts@Example.com'])->assertSessionHasNoErrors();
        expect($this->settings->notifyByEmail())->toBeFalse()
            ->and($this->settings->smsDriver())->toBe(SmsDriver::Twilio)
            ->and($this->settings->reminderHoursBefore())->toBe(12)
            ->and($this->settings->adminNotificationEmail())->toBe('alerts@example.com');

        config(['services.twilio.sid' => null]);
        $save(['sms_driver' => 'twilio'])->assertSessionHasErrors('sms_driver');
        $save(['admin_notification_email' => 'not-an-email'])->assertSessionHasErrors('admin_notification_email');

        $save(['sms_driver' => '', 'reminder_hours_before' => '', 'admin_notification_email' => ''])->assertSessionHasNoErrors();
        expect($this->settings->smsDriver())->toBeNull()
            ->and($this->settings->reminderHoursBefore())->toBe(Settings::DEFAULT_REMINDER_HOURS_BEFORE)
            ->and($this->settings->adminNotificationEmail())->toBeNull();
    });
});

describe('customer SMS preference', function () {
    it('shows the SMS choice only when SMS is on, and saves an opt-out', function () {
        $this->actingAs($this->user)->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('sms', null));

        $this->settings->setSmsDriver(SmsDriver::Log);
        $this->actingAs($this->user)->get(route('account'))
            ->assertInertia(fn (Assert $page) => $page->where('sms.opted_out', false)->where('sms.phone', '+919812345678'));

        $this->actingAs($this->user)->put(route('account.notifications.update'), ['sms' => false])->assertRedirect(route('account'));
        expect($this->user->fresh()->sms_opt_out)->toBeTrue();

        $this->actingAs($this->user)->put(route('account.notifications.update'), ['sms' => 'maybe'])->assertSessionHasErrors('sms');
    });

    it('requires sign-in', function () {
        $this->put(route('account.notifications.update'), ['sms' => true])->assertRedirect(route('login'));
    });
});
