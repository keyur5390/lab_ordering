<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (hosted page). Payments are confirmed on return and by the
 * checkout.session.completed / async_payment_succeeded webhooks.
 */
class StripeGateway implements PaymentGateway
{
    use SendsGatewayRequests;

    /** Webhooks older than this are rejected, as Stripe recommends. */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function createCheckout(Payment $payment, Order $order, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        $session = $this->send('Stripe', fn () => $this->client()
            ->withHeaders(['Idempotency-Key' => "payment-{$payment->id}"])
            ->post('/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $payment->id,
                'customer_email' => $order->user->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'unit_amount' => $payment->amount,
                        'product_data' => ['name' => "Order {$order->number}"],
                    ],
                ]],
                'metadata' => ['order' => $order->number, 'payment_id' => $payment->id],
                'success_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]));

        return new CheckoutSession($session['id'], $session['url']);
    }

    public function paidOnReturn(Payment $payment, Request $request): ?string
    {
        $session = $this->send('Stripe', fn () => $this->client()->get("/v1/checkout/sessions/{$payment->reference}"));

        return ($session['payment_status'] ?? null) === 'paid' ? ($session['payment_intent'] ?? null) : null;
    }

    public function paidFromWebhook(Request $request): ?PaidCheckout
    {
        $this->verifySignature($request);

        $event = $request->json()->all();
        $session = $event['data']['object'] ?? [];

        if (! in_array($event['type'] ?? null, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            || ($session['payment_status'] ?? null) !== 'paid'
            || ! isset($session['id'], $session['payment_intent'])) {
            return null;
        }

        return new PaidCheckout($session['id'], $session['payment_intent']);
    }

    public function refund(Payment $payment, int $amount): GatewayRefund
    {
        $refund = $this->send('Stripe', fn () => $this->client()->post('/v1/refunds', [
            'payment_intent' => $payment->gateway_payment_id,
            'amount' => $amount,
        ]));

        return new GatewayRefund($refund['id'] ?? null, match ($refund['status'] ?? null) {
            'succeeded' => RefundStatus::Succeeded,
            'failed', 'canceled' => RefundStatus::Failed,
            default => RefundStatus::Pending,
        });
    }

    /**
     * Stripe-Signature: t=<timestamp>,v1=<hex HMAC-SHA256 of "<timestamp>.<body>">[,v1=…]
     */
    private function verifySignature(Request $request): void
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", (string) config('services.stripe.webhook_secret'));

        if (! ctype_digit((string) $timestamp)
            || abs(now()->getTimestamp() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS
            || ! collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature))) {
            throw new InvalidWebhookSignature;
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.stripe.base_url'))
            ->withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->acceptJson()
            ->timeout(15);
    }
}
