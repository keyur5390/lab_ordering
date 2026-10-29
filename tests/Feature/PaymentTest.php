<?php

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\PaymentGatewayName;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ServiceAreaService;
use App\Services\SlotService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.webhook_secret' => 'whsec_test',
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'rzp_secret',
        'services.razorpay.webhook_secret' => 'rzp_webhook_secret',
    ]);

    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setTimezone('Asia/Kolkata');
    $this->settings->setPaymentGateway(PaymentGatewayName::Stripe);
    $this->settings->setPaymentHoldMinutes(20);
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->user = User::factory()->create();
    $this->patient = $this->user->patients()->sole();
    $this->patient->update(['date_of_birth' => '1990-01-01', 'sex' => 'female']);
    $this->area = ServiceArea::factory()->scheduled(capacity: 1)->create(['country' => 'IN']);
    app(ServiceAreaService::class)->syncPostcodes($this->area, ['400001']);
    $this->address = Address::factory()->for($this->user)->create(['postcode' => '400001', 'country' => 'IN']);
    $this->test = LabTest::factory()->create(['price_amount' => 40000]);
    $this->item = Cart::create(['user_id' => $this->user->id])->items()->create(['lab_test_id' => $this->test->id]);

    // Friday 30 Oct, 09:00 Kolkata.
    $this->slot = '2026-10-30T09:00:00+05:30';
});

function payOnline(array $overrides = [])
{
    return test()->actingAs(test()->user)->post(route('checkout.store'), [
        'items' => [test()->item->id => [test()->patient->id]],
        'address_id' => test()->address->id,
        'slot_starts_at' => test()->slot,
        'payment_method' => 'online',
        ...$overrides,
    ]);
}

/** Stripe's API: new sessions are cs_new_1, cs_new_2…; looking one up reports it as $paymentStatus. */
function fakeStripe(string $paymentStatus = 'paid'): void
{
    $sessions = 0;
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => function () use (&$sessions) {
            $id = 'cs_new_'.++$sessions;

            return Http::response(['id' => $id, 'url' => "https://checkout.stripe.com/c/pay/{$id}"]);
        },
        'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_test_1', 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_1']),
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded']),
    ]);
}

function stripeWebhook(array $event, ?int $timestamp = null, string $secret = 'whsec_test')
{
    $payload = json_encode($event);
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return test()->call('POST', route('webhooks.payments', 'stripe'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $payload);
}

function stripeCompleted(string $session = 'cs_test_1', string $intent = 'pi_1'): array
{
    return ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $session, 'payment_status' => 'paid', 'payment_intent' => $intent]]];
}

/** An online order awaiting payment, with a Stripe checkout started. */
function pendingOrder(array $attributes = [], string $reference = 'cs_test_1'): Order
{
    $order = Order::factory()->for(test()->user)->for(test()->area)
        ->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))
        ->create(['status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Online, 'payment_expires_at' => now()->addMinutes(20), ...$attributes]);
    Payment::factory()->for($order)->create(['reference' => $reference, 'amount' => $order->total_amount]);

    return $order;
}

/** A confirmed order paid online through Stripe. */
function paidOrder(): Order
{
    $order = Order::factory()->for(test()->user)->for(test()->area)
        ->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))
        ->create(['payment_method' => PaymentMethod::Online, 'total_amount' => 50000]);
    Payment::factory()->paid()->for($order)->create(['gateway_payment_id' => 'pi_1', 'amount' => 50000]);

    return $order;
}

describe('checkout', function () {
    it('offers online payment only when a gateway is chosen and its keys are set', function () {
        $page = fn () => $this->actingAs($this->user)->get(route('checkout.show'));

        $page()->assertInertia(fn (Assert $page) => $page->where('onlinePayment', true)->where('paymentHoldMinutes', 20));

        config(['services.stripe.secret' => null]);
        $page()->assertInertia(fn (Assert $page) => $page->where('onlinePayment', false));

        $this->settings->setPaymentGateway(null);
        config(['services.stripe.secret' => 'sk_test_123']);
        $page()->assertInertia(fn (Assert $page) => $page->where('onlinePayment', false));
        payOnline()->assertSessionHasErrors('payment_method');
        expect(Order::count())->toBe(0);
    });

    it('places an unpaid order holding the slot and sends the customer to Stripe', function () {
        fakeStripe();

        payOnline()->assertRedirect('https://checkout.stripe.com/c/pay/cs_new_1');

        $order = Order::sole();
        expect($order->status)->toBe(OrderStatus::PendingPayment)
            ->and($order->payment_method)->toBe(PaymentMethod::Online)
            ->and($order->payment_expires_at->equalTo(now()->addMinutes(20)))->toBeTrue()
            ->and($order->payments()->sole()->only(['reference', 'amount', 'currency']))->toBe(['reference' => 'cs_new_1', 'amount' => 40000, 'currency' => 'INR'])
            ->and(Cart::where('user_id', $this->user->id)->sole()->items()->count())->toBe(0);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['line_items'][0]['price_data']['unit_amount'] === 40000
            && $request['line_items'][0]['price_data']['currency'] === 'inr'
            && $request['success_url'] === route('orders.payments.return', [$order, $order->payments()->sole()])
            && $request->hasHeader('Authorization', 'Bearer sk_test_123'));
    });

    it('sends Inertia visits to the gateway with a full-page redirect', function () {
        fakeStripe();

        $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('checkout.store'), [
                'items' => [$this->item->id => [$this->patient->id]],
                'address_id' => $this->address->id,
                'slot_starts_at' => $this->slot,
                'payment_method' => 'online',
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/cs_new_1');
    });

    it('keeps the order and offers a retry when the gateway is down', function () {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $response = payOnline();

        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order))->assertSessionHasErrors('payment');
        expect($order->status)->toBe(OrderStatus::PendingPayment)
            ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed);

        $this->actingAs($this->user)->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.can_pay', true)->where('order.payment_expires_at', '2026-10-29T06:20:00+05:30'));
    });
});

describe('confirming payments', function () {
    it('confirms the order when the customer returns from a paid Stripe checkout', function () {
        fakeStripe();
        $order = pendingOrder();

        $this->actingAs($this->user)
            ->get(route('orders.payments.return', [$order, $order->payments()->sole()]))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'payment was received'));

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($order->fresh()->payment_expires_at)->toBeNull()
            ->and($order->payments()->sole()->only(['status', 'gateway_payment_id']))->toBe(['status' => PaymentStatus::Paid, 'gateway_payment_id' => 'pi_1']);
    });

    it('leaves the order waiting when the return shows no payment yet', function () {
        fakeStripe('unpaid');
        $order = pendingOrder();

        $this->actingAs($this->user)->get(route('orders.payments.return', [$order, $order->payments()->sole()]))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'waiting'));

        expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    });

    it('protects the return page', function () {
        $order = pendingOrder();
        $other = pendingOrder(['user_id' => User::factory()->create()->id], 'cs_test_2');

        $this->actingAs(User::factory()->create())->get(route('orders.payments.return', [$order, $order->payments()->sole()]))->assertForbidden();
        $this->actingAs($this->user)->get(route('orders.payments.return', [$order, $other->payments()->sole()]))->assertNotFound();
    });

    it('confirms by Stripe webhook, once, and rejects bad signatures', function () {
        Http::fake();
        $order = pendingOrder();

        stripeWebhook(stripeCompleted(), secret: 'wrong')->assertStatus(400);
        stripeWebhook(stripeCompleted(), timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(400);
        expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

        stripeWebhook(['type' => 'checkout.session.expired', 'data' => ['object' => ['id' => 'cs_test_1']]])->assertOk();
        expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

        stripeWebhook(stripeCompleted())->assertOk();
        stripeWebhook(stripeCompleted())->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($order->payments()->sole()->gateway_payment_id)->toBe('pi_1');
        Http::assertNothingSent();
    });

    it('rejects webhooks for unknown gateways', function () {
        $this->post('/webhooks/payments/paypal')->assertNotFound();
    });
});

describe('razorpay', function () {
    beforeEach(fn () => $this->settings->setPaymentGateway(PaymentGatewayName::Razorpay));

    it('creates a payment link and confirms a correctly signed return', function () {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_1', 'short_url' => 'https://rzp.io/i/abc'])]);

        payOnline()->assertRedirect('https://rzp.io/i/abc');
        $order = Order::sole();
        $payment = $order->payments()->sole();
        Http::assertSent(fn (HttpRequest $request) => $request['amount'] === 40000
            && $request['currency'] === 'INR'
            && $request['reference_id'] === "{$order->number}-{$payment->id}"
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:rzp_secret')));

        $query = [
            'razorpay_payment_id' => 'pay_1',
            'razorpay_payment_link_id' => 'plink_1',
            'razorpay_payment_link_reference_id' => "{$order->number}-{$payment->id}",
            'razorpay_payment_link_status' => 'paid',
        ];
        $signed = fn (array $query) => [...$query, 'razorpay_signature' => hash_hmac('sha256', implode('|', [
            $query['razorpay_payment_link_id'], $query['razorpay_payment_link_reference_id'], $query['razorpay_payment_link_status'], $query['razorpay_payment_id'],
        ]), 'rzp_secret')];
        $return = fn (array $query) => $this->actingAs($this->user)->get(route('orders.payments.return', [$order, $payment, ...$query]));

        $return([...$signed($query), 'razorpay_payment_id' => 'pay_forged']);
        expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

        $return($signed($query));
        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($payment->fresh()->gateway_payment_id)->toBe('pay_1');
    });

    it('confirms by signed webhook', function () {
        $order = pendingOrder();
        $order->payments()->sole()->update(['gateway' => PaymentGatewayName::Razorpay, 'reference' => 'plink_1']);
        $payload = json_encode(['event' => 'payment_link.paid', 'payload' => [
            'payment_link' => ['entity' => ['id' => 'plink_1']],
            'payment' => ['entity' => ['id' => 'pay_1']],
        ]]);
        $send = fn (string $signature) => $this->call('POST', route('webhooks.payments', 'razorpay'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], content: $payload);

        $send('forged')->assertStatus(400);
        $send(hash_hmac('sha256', $payload, 'rzp_webhook_secret'))->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    });

    it('refunds through the payments API', function () {
        Http::fake(['api.razorpay.com/v1/payments/pay_1/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'])]);
        $order = paidOrder();
        $order->payments()->sole()->update(['gateway' => PaymentGatewayName::Razorpay, 'gateway_payment_id' => 'pay_1']);

        expect(app(PaymentService::class)->refund($order, 20000))->toBeTrue();

        $refund = $order->payments()->sole()->refunds()->sole();
        expect($refund->status)->toBe(RefundStatus::Succeeded)->and($refund->gateway_refund_id)->toBe('rfnd_1');
        Http::assertSent(fn (HttpRequest $request) => $request['amount'] === 20000);
    });
});

describe('slot holds', function () {
    it('holds the slot until the payment hold ends, even before the order is cancelled', function () {
        pendingOrder();
        $slot = fn () => collect(app(SlotService::class)->days($this->area))->firstWhere('date', '2026-10-30')['slots'][1];

        expect($slot()['time'])->toBe('09:00')->and($slot()['status'])->toBe('full');

        $this->travel(20)->minutes();
        expect($slot()['status'])->toBe('available');
    });

    it('cancels unpaid orders whose hold has passed', function () {
        $expired = pendingOrder();
        $current = pendingOrder(['payment_expires_at' => now()->addMinutes(30)], 'cs_test_2');
        $confirmed = Order::factory()->for($this->area)->create();
        $this->travel(21)->minutes();

        $this->artisan('orders:expire-unpaid')->expectsOutput('Cancelled 1 unpaid order.')->assertSuccessful();

        expect($expired->fresh()->status)->toBe(OrderStatus::Cancelled)
            ->and($expired->fresh()->cancelled_at)->not->toBeNull()
            ->and($current->fresh()->status)->toBe(OrderStatus::PendingPayment)
            ->and($confirmed->fresh()->status)->toBe(OrderStatus::Confirmed);
    });

    it('lets the customer pay again only while the hold lasts', function () {
        fakeStripe();
        $order = pendingOrder();

        $this->actingAs(User::factory()->create())->post(route('orders.payments.store', $order))->assertForbidden();
        $this->actingAs($this->user)->post(route('orders.payments.store', $order))->assertRedirect('https://checkout.stripe.com/c/pay/cs_new_1');
        expect($order->payments()->count())->toBe(2);

        $this->travel(20)->minutes();
        $this->actingAs($this->user)->post(route('orders.payments.store', $order))->assertForbidden();
    });

    it('confirms a late payment when the slot is still free', function () {
        Http::fake();
        $order = pendingOrder();
        $this->travel(25)->minutes();

        stripeWebhook(stripeCompleted())->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
        Http::assertNothingSent();
    });

    it('refunds a late payment in full when the slot was taken meanwhile', function () {
        fakeStripe();
        $order = pendingOrder();
        $this->travel(25)->minutes();
        Order::factory()->for($this->area)->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))->create();

        stripeWebhook(stripeCompleted())->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
            ->and($order->payments()->sole()->refunds()->sole()->amount)->toBe(50000);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.stripe.com/v1/refunds'
            && $request['payment_intent'] === 'pi_1' && $request['amount'] === 50000);
    });

    it('refunds a payment for an order that was already cancelled, or paid twice', function () {
        fakeStripe();
        $cancelled = pendingOrder(['status' => OrderStatus::Cancelled]);
        stripeWebhook(stripeCompleted())->assertOk();
        expect($cancelled->fresh()->status)->toBe(OrderStatus::Refunded);

        $order = paidOrder();
        Payment::factory()->for($order)->create(['reference' => 'cs_test_2', 'amount' => 50000]);
        stripeWebhook(stripeCompleted('cs_test_2', 'pi_2'))->assertOk();

        $refund = $order->payments()->where('reference', 'cs_test_2')->sole()->refunds()->sole();
        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed)
            ->and($refund->reason)->toBe('Paid twice for the same order')
            ->and(app(PaymentService::class)->amounts($order))->toBe(['paid' => 100000, 'refunded' => 50000, 'refundable' => 50000]);
    });
});

describe('refunds', function () {
    beforeEach(fn () => $this->settings->setCancellationCutoffHours(2));

    it('refunds in full when the customer cancels a paid order', function () {
        fakeStripe();
        $order = paidOrder();

        $this->actingAs($this->user)
            ->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment.refundable', 50000)->where('order.can_refund', false));

        $this->actingAs($this->user)->post(route('orders.cancel', $order))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'full refund is on its way'));

        $refund = $order->payments()->sole()->refunds()->sole();
        expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
            ->and($refund->amount)->toBe(50000)
            ->and($refund->user_id)->toBeNull();
    });

    it('keeps the order cancelled and records the failure when the gateway refuses a refund', function () {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'nope']], 402)]);
        $order = paidOrder();

        $this->actingAs($this->user)->post(route('orders.cancel', $order))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'our team will process it'));

        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
            ->and($order->payments()->sole()->refunds()->sole()->status)->toBe(RefundStatus::Failed)
            ->and(app(PaymentService::class)->amounts($order)['refundable'])->toBe(50000);
    });

    it('lets admins refund part of a payment, up to what is left', function () {
        fakeStripe();
        $order = paidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.can_refund', true)->where('order.payment.paid', 50000));

        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order), ['amount' => '120.50', 'reason' => 'Test not done'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasNoErrors();

        $refund = $order->payments()->sole()->refunds()->sole();
        expect($refund->only(['amount', 'reason', 'user_id']))->toBe(['amount' => 12050, 'reason' => 'Test not done', 'user_id' => $admin->id])
            ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);

        foreach (['380.00', '0', 'abc'] as $amount) {
            $this->actingAs($admin)->post(route('admin.orders.refunds.store', $order), ['amount' => $amount])->assertSessionHasErrors('amount');
        }
        expect($order->payments()->sole()->refunds()->count())->toBe(1);
    });

    it('refunds the rest in full when an admin cancels', function () {
        fakeStripe();
        $order = paidOrder();
        $admin = User::factory()->admin()->create();
        app(PaymentService::class)->refund($order, 10000, $admin);

        $this->actingAs($admin)->post(route('admin.orders.cancel', $order))->assertSessionHas('status', 'Order cancelled and refunded in full.');

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
            ->and($order->payments()->sole()->refunds()->pluck('amount')->all())->toBe([10000, 40000]);
    });

    it('allows refunds only for admins, and only of money paid online', function () {
        $order = paidOrder();
        $unpaid = Order::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->user)->post(route('admin.orders.refunds.store', $order), ['amount' => '10'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.orders.refunds.store', $unpaid), ['amount' => '10'])->assertForbidden();
    });
});

describe('settings', function () {
    it('saves the gateway and hold, and only allows gateways whose keys are set', function () {
        $admin = User::factory()->admin()->create();
        $save = fn (array $data) => $this->actingAs($admin)->put(route('admin.settings.update'), ['currency' => 'INR', 'timezone' => 'Asia/Kolkata', ...$data]);

        $save(['payment_gateway' => 'razorpay', 'payment_hold_minutes' => '30'])->assertSessionHasNoErrors();
        expect($this->settings->paymentGateway())->toBe(PaymentGatewayName::Razorpay)
            ->and($this->settings->paymentHoldMinutes())->toBe(30);

        config(['services.stripe.webhook_secret' => null]);
        $save(['payment_gateway' => 'stripe'])->assertSessionHasErrors('payment_gateway');
        $save(['payment_hold_minutes' => '2'])->assertSessionHasErrors('payment_hold_minutes');

        $save(['payment_gateway' => '', 'payment_hold_minutes' => ''])->assertSessionHasNoErrors();
        expect($this->settings->paymentGateway())->toBeNull()
            ->and($this->settings->paymentHoldMinutes())->toBe(Settings::DEFAULT_PAYMENT_HOLD_MINUTES);

        $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('gateways.0.configured', false)->where('gateways.1.configured', true));
    });
});
