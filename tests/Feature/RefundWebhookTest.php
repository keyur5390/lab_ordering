<?php

use App\Enums\Currency;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentGatewayName;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Models\CreditNote;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Notifications\OrderNotification;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk', 'services.stripe.webhook_secret' => 'whsec_test',
        'services.razorpay.key_id' => 'k', 'services.razorpay.key_secret' => 's', 'services.razorpay.webhook_secret' => 'rzp_hook',
    ]);
    Notification::fake();
    app(Settings::class)->setCurrency(Currency::INR);
    app(Settings::class)->setAdminNotificationEmail('ops@example.com');

    $this->order = Order::factory()->create(['payment_method' => PaymentMethod::Online, 'total_amount' => 50000]);
    $this->payment = Payment::factory()->paid()->for($this->order)->create(['amount' => 50000, 'gateway_payment_id' => 'pi_1']);
    app(InvoiceService::class)->issue($this->order, $this->payment);

    // The gateway accepts the refund but hasn't finished it.
    Http::fake([
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'pending']),
        'api.razorpay.com/*' => Http::response(['id' => 'rfnd_1', 'status' => 'pending']),
    ]);
});

function stripeRefundEvent(string $status, string $id = 're_1', string $secret = 'whsec_test')
{
    $payload = json_encode(['type' => 'charge.refund.updated', 'data' => ['object' => ['id' => $id, 'status' => $status]]]);
    $t = now()->getTimestamp();

    return test()->call('POST', route('webhooks.payments', 'stripe'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret),
    ], content: $payload);
}

function razorpayRefundEvent(string $event, string $id = 'rfnd_1')
{
    $payload = json_encode(['event' => $event, 'payload' => ['refund' => ['entity' => ['id' => $id]]]]);

    return test()->call('POST', route('webhooks.payments', 'razorpay'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'rzp_hook'),
    ], content: $payload);
}

it('keeps a processing refund counted, and issues its credit note when it succeeds', function () {
    app(OrderService::class)->cancel($this->order);
    $refund = Refund::sole();

    expect($refund->status)->toBe(RefundStatus::Pending)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(CreditNote::count())->toBe(0);

    stripeRefundEvent('succeeded')->assertOk();
    stripeRefundEvent('succeeded')->assertOk();

    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and(CreditNote::sole()->amount)->toBe(50000);
});

it('makes the money refundable again when a refund fails, and alerts admins', function () {
    app(OrderService::class)->cancel($this->order);

    stripeRefundEvent('failed')->assertOk();

    expect(Refund::sole()->status)->toBe(RefundStatus::Failed)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(app(PaymentService::class)->amounts($this->order->fresh())['refundable'])->toBe(50000)
        ->and(CreditNote::count())->toBe(0);
    Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::AdminRefundFailed);

    // A failed refund doesn't change again.
    stripeRefundEvent('succeeded');
    expect(Refund::sole()->status)->toBe(RefundStatus::Failed);
});

it('handles Razorpay refund events', function () {
    $this->payment->update(['gateway' => PaymentGatewayName::Razorpay, 'gateway_payment_id' => 'pay_1']);
    app(PaymentService::class)->refund($this->order, 20000);
    app(PaymentService::class)->refund($this->order, 10000);
    Refund::latest('id')->first()->update(['gateway_refund_id' => 'rfnd_2']);

    razorpayRefundEvent('refund.processed')->assertOk();
    razorpayRefundEvent('refund.failed', 'rfnd_2')->assertOk();

    expect(Refund::orderBy('id')->pluck('status')->all())->toBe([RefundStatus::Succeeded, RefundStatus::Failed])
        ->and(CreditNote::sole()->amount)->toBe(20000);
});

it('ignores unknown refunds and refunds from the other gateway, and rejects bad signatures', function () {
    app(PaymentService::class)->refund($this->order, 20000);

    stripeRefundEvent('succeeded', 're_unknown')->assertOk();
    razorpayRefundEvent('refund.processed', 're_1')->assertOk();
    expect(Refund::sole()->status)->toBe(RefundStatus::Pending);

    stripeRefundEvent('succeeded', 're_1', 'wrong')->assertStatus(400);
    expect(Refund::sole()->status)->toBe(RefundStatus::Pending);
});
