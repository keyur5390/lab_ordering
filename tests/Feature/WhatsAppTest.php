<?php

use App\Enums\Currency;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Enums\UserRole;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\OrderNotification;
use App\Services\OrderNotifier;
use App\Settings\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const SID = 'HX0123456789abcdef0123456789abcdef';

beforeEach(function () {
    config(['services.twilio.sid' => 'AC1', 'services.twilio.token' => 't', 'services.twilio.whatsapp_from' => '+14155238886']);
    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setWhatsAppEnabled(true);

    $this->customer = User::factory()->create(['name' => 'Asha Rao', 'whatsapp_opt_in' => true]);
    $this->customer->forceFill(['whatsapp_opt_in' => true, 'phone' => '+919800000001', 'phone_verified_at' => now()])->save();
    $this->order = Order::factory()->for($this->customer)->create();
    WhatsAppTemplate::create(['event' => NotificationEvent::OrderConfirmed, 'content_sid' => SID, 'variables' => ['customer_name', 'order_number'], 'is_active' => true]);
    $this->admin = User::factory()->admin()->create();
});

function whatsAppVia(?User $user = null): bool
{
    $user ??= test()->customer->fresh();

    return in_array(WhatsAppChannel::class, (new OrderNotification(NotificationEvent::OrderConfirmed, test()->order))->via($user), true);
}

describe('sending', function () {
    it('sends the event\'s template with its variables through Twilio, and logs it', function () {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        app(OrderNotifier::class)->placed($this->order);

        Http::assertSent(fn (Request $request) => $request['To'] === 'whatsapp:+919800000001'
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['ContentSid'] === SID
            && json_decode($request['ContentVariables'], true) === ['1' => 'Asha Rao', '2' => $this->order->number]);
        expect(NotificationLog::where('channel', NotificationChannel::WhatsApp)->sole())
            ->status->toBe('sent')
            ->recipient->toBe('+919800000001');
    });

    it('uses a Messaging Service when the sender is one', function () {
        config(['services.twilio.whatsapp_from' => 'MG0123']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        app(OrderNotifier::class)->placed($this->order);

        Http::assertSent(fn (Request $request) => isset($request['ContentSid']) && $request['MessagingServiceSid'] === 'MG0123' && ! isset($request['From']));
    });

    it('only goes to customers who opted in and have a number, for events with an active template', function () {
        expect(whatsAppVia())->toBeTrue();

        $this->customer->forceFill(['whatsapp_opt_in' => false])->save();
        expect(whatsAppVia())->toBeFalse();
        $this->customer->forceFill(['whatsapp_opt_in' => true, 'phone' => null, 'phone_verified_at' => null])->save();
        Patient::where('user_id', $this->customer->id)->update(['phone' => null]);
        expect(whatsAppVia())->toBeFalse();
        $this->customer->forceFill(['phone' => '+919800000001', 'phone_verified_at' => now()])->save();

        WhatsAppTemplate::query()->update(['is_active' => false]);
        expect(whatsAppVia())->toBeFalse();
        WhatsAppTemplate::query()->update(['is_active' => true]);

        $this->settings->setWhatsAppEnabled(false);
        expect(whatsAppVia())->toBeFalse();
        $this->settings->setWhatsAppEnabled(true);
        config(['services.twilio.whatsapp_from' => null]);
        expect(whatsAppVia())->toBeFalse();
    });

    it('sends a dash for an empty value, which Twilio would reject', function () {
        WhatsAppTemplate::query()->update(['variables' => ['collector_name']]);

        expect((new OrderNotification(NotificationEvent::OrderConfirmed, $this->order))->toWhatsApp($this->customer)['variables'])->toBe(['1' => '-']);
    });

    it('can be resent from the history on its own', function () {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM2'], 201)]);
        $this->settings->setNotifyByEmail(false);
        // Failed: logged through the queue's failure hook in production; recorded directly here.
        $log = NotificationLog::create(['order_id' => $this->order->id, 'event' => NotificationEvent::OrderConfirmed, 'channel' => NotificationChannel::WhatsApp, 'recipient' => '+919800000001', 'status' => 'failed']);

        $this->actingAs($this->admin)->post(route('admin.notifications.resend', $log))->assertSessionHasNoErrors();

        expect(NotificationLog::where('channel', NotificationChannel::WhatsApp)->where('status', 'sent')->count())->toBe(1)
            ->and(NotificationLog::where('status', 'sent')->count())->toBe(1);
        Http::assertSentCount(1);
    });
});

describe('admin templates', function () {
    it('lists customer events only, with what is saved', function () {
        $this->actingAs($this->admin)->get(route('admin.notifications.whatsapp.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/whatsapp')
                ->where('enabled', true)
                ->has('events', count(array_filter(NotificationEvent::cases(), fn ($event) => ! $event->forAdmins())))
                ->where('events.0.value', 'order_confirmed')
                ->where('events.0.content_sid', SID)
                ->where('events.0.variables', 'customer_name, order_number'));
    });

    it('saves SIDs and variables, removes cleared ones, and checks both', function () {
        $sid = 'HX'.str_repeat('a', 32);
        $save = fn (array $templates) => $this->actingAs($this->admin)->put(route('admin.notifications.whatsapp.update'), ['templates' => $templates]);

        $save(['payment_received' => ['content_sid' => 'nope', 'variables' => '', 'is_active' => true]])->assertSessionHasErrors('templates.payment_received.content_sid');
        $save(['payment_received' => ['content_sid' => $sid, 'variables' => 'amount, password', 'is_active' => true]])->assertSessionHasErrors('templates.payment_received.variables');

        $save([
            'order_confirmed' => ['content_sid' => '', 'variables' => '', 'is_active' => true],
            'payment_received' => ['content_sid' => $sid, 'variables' => ' amount ,order_number ', 'is_active' => false],
        ])->assertRedirect(route('admin.notifications.whatsapp.edit'));

        expect(WhatsAppTemplate::where('event', 'order_confirmed')->exists())->toBeFalse();
        $saved = WhatsAppTemplate::where('event', 'payment_received')->sole();
        expect($saved->content_sid)->toBe($sid)
            ->and($saved->variables)->toBe(['amount', 'order_number'])
            ->and($saved->is_active)->toBeFalse();
    });

    it('is for admins only', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))->get(route('admin.notifications.whatsapp.edit'))->assertForbidden();
        $this->actingAs($this->customer)->put(route('admin.notifications.whatsapp.update'), ['templates' => []])->assertForbidden();
    });

    it('switches WhatsApp on and off in Settings', function () {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'UTC', 'whatsapp_enabled' => false])->assertSessionHasNoErrors();
        expect($this->settings->whatsAppEnabled())->toBeFalse();
    });
});

describe('customer opt-in', function () {
    it('lets customers opt in and out on their account page, separately from SMS', function () {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('whatsapp.opted_in', false));
        $this->actingAs($customer)->put(route('account.notifications.update'), ['whatsapp' => true])->assertRedirect(route('account'));
        expect($customer->fresh()->whatsapp_opt_in)->toBeTrue()->and($customer->fresh()->sms_opt_out)->toBeFalse();

        $this->actingAs($customer)->put(route('account.notifications.update'), ['sms' => false]);
        expect($customer->fresh()->whatsapp_opt_in)->toBeTrue()->and($customer->fresh()->sms_opt_out)->toBeTrue();

        $this->actingAs($customer)->put(route('account.notifications.update'), [])->assertSessionHasErrors();
    });

    it('is not offered while WhatsApp is off', function () {
        $this->settings->setWhatsAppEnabled(false);

        $this->actingAs($this->customer)->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('whatsapp', null));
    });
});
