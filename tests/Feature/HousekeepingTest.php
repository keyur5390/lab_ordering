<?php

use App\Enums\CollectionPaymentMethod;
use App\Enums\Currency;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app(Settings::class)->setCurrency(Currency::INR);
});

describe('guest carts', function () {
    it('deletes guest carts nobody has changed for 7 days, and never customers\' carts', function () {
        $test = LabTest::factory()->create();
        $idle = Cart::create(['guest_token' => 'idle']);
        $idle->items()->create(['lab_test_id' => $test->id]);
        $recent = Cart::create(['guest_token' => 'recent']);
        $customer = Cart::create(['user_id' => User::factory()->create()->id]);
        Cart::query()->update(['updated_at' => now()->subDays(8)]);
        $recent->forceFill(['updated_at' => now()->subDays(6)])->saveQuietly();

        $this->artisan('model:prune', ['--model' => [Cart::class]])->assertSuccessful();

        expect(Cart::pluck('id')->sort()->values()->all())->toBe(collect([$recent->id, $customer->id])->sort()->values()->all())
            ->and($idle->items()->count())->toBe(0);
    });

    it('counts adding or removing an item as activity', function () {
        $cart = Cart::create(['guest_token' => 'busy']);
        $cart->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();

        $item = $cart->items()->create(['lab_test_id' => LabTest::factory()->create()->id]);
        expect($cart->fresh()->updated_at->isToday())->toBeTrue();

        $cart->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();
        $item->delete();
        expect($cart->fresh()->updated_at->isToday())->toBeTrue();
    });

    it('runs every day', function () {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'model:prune') && str_contains($event->command, 'Cart'));

        expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
    });
});

describe('invoice with the payment email', function () {
    beforeEach(function () {
        $this->customer = User::factory()->create();
    });

    it('attaches the invoice to the online "payment received" email', function () {
        $order = Order::factory()->for($this->customer)->create(['status' => OrderStatus::PendingPayment, 'payment_method' => PaymentMethod::Online, 'payment_expires_at' => now()->addMinutes(15)]);
        $payment = Payment::factory()->for($order)->create(['amount' => $order->total_amount]);
        Notification::fake();

        app(PaymentService::class)->confirm($payment, 'pi_123');

        $invoice = $order->fresh()->invoice;
        Notification::assertSentTo($this->customer, OrderNotification::class, function (OrderNotification $n) use ($invoice) {
            if ($n->event !== NotificationEvent::PaymentReceived) {
                return false;
            }
            $mail = $n->toMail($this->customer);

            return collect($mail->rawAttachments)->pluck('name')->all() === ["{$invoice->number}.pdf"]
                && str_starts_with($mail->rawAttachments[0]['data'], '%PDF');
        });
    });

    it('emails the invoice when a pay-at-collection payment is recorded', function () {
        $order = Order::factory()->for($this->customer)->status(OrderStatus::SampleCollected)->create();
        Notification::fake();

        app(PaymentService::class)->recordCollectedPayment($order, CollectionPaymentMethod::Cash);

        $invoice = $order->fresh()->invoice;
        Notification::assertSentTo($this->customer, OrderNotification::class, function (OrderNotification $n) use ($invoice, $order) {
            $mail = $n->event === NotificationEvent::PaymentCollected ? $n->toMail($this->customer) : null;

            return $mail
                && $mail->subject === "Payment received for order {$order->number}"
                && $n->amount === $order->total_amount
                && collect($mail->rawAttachments)->pluck('name')->all() === ["{$invoice->number}.pdf"];
        });
    });

    it('sends no invoice with other emails', function () {
        $order = Order::factory()->for($this->customer)->create();
        app(InvoiceService::class)->issue($order);

        expect((new OrderNotification(NotificationEvent::OrderConfirmed, $order->fresh()))->toMail($this->customer)->rawAttachments)->toBe([]);
    });
});
