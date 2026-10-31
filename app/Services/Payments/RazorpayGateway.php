<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay Payment Links (hosted page). Payments are confirmed on return (signed callback) and by
 * the payment_link.paid webhook.
 */
class RazorpayGateway implements PaymentGateway
{
    use SendsGatewayRequests;

    public function createCheckout(Payment $payment, Order $order, string $returnUrl, string $cancelUrl): CheckoutSession
    {
        // Payment Links have no cancel URL: a customer who leaves comes back to the order page themselves.
        $link = $this->send('Razorpay', fn () => $this->client()->post('/v1/payment_links', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            // Unique per Razorpay account.
            'reference_id' => "{$order->number}-{$payment->id}",
            'description' => "Order {$order->number}",
            'customer' => ['name' => $order->user->name, 'email' => $order->user->email],
            'notify' => ['sms' => false, 'email' => false],
            'callback_url' => $returnUrl,
            'callback_method' => 'get',
        ]));

        return new CheckoutSession($link['id'], $link['short_url']);
    }

    /**
     * The callback is signed: HMAC-SHA256 of "link_id|reference_id|status|payment_id" with the key secret.
     */
    public function paidOnReturn(Payment $payment, Request $request): ?string
    {
        $linkId = (string) $request->query('razorpay_payment_link_id');
        $paymentId = (string) $request->query('razorpay_payment_id');
        $status = (string) $request->query('razorpay_payment_link_status');
        $expected = hash_hmac('sha256', implode('|', [
            $linkId,
            (string) $request->query('razorpay_payment_link_reference_id'),
            $status,
            $paymentId,
        ]), (string) config('services.razorpay.key_secret'));

        if ($linkId !== $payment->reference || $status !== 'paid' || $paymentId === ''
            || ! hash_equals($expected, (string) $request->query('razorpay_signature'))) {
            return null;
        }

        return $paymentId;
    }

    public function paidFromWebhook(Request $request): ?PaidCheckout
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('services.razorpay.webhook_secret'));
        if (! hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            throw new InvalidWebhookSignature;
        }

        $event = $request->json()->all();
        $linkId = $event['payload']['payment_link']['entity']['id'] ?? null;
        $paymentId = $event['payload']['payment']['entity']['id'] ?? null;

        if (($event['event'] ?? null) !== 'payment_link.paid' || ! $linkId || ! $paymentId) {
            return null;
        }

        return new PaidCheckout($linkId, $paymentId);
    }

    public function refund(Payment $payment, int $amount): GatewayRefund
    {
        $refund = $this->send('Razorpay', fn () => $this->client()->post("/v1/payments/{$payment->gateway_payment_id}/refund", [
            'amount' => $amount,
        ]));

        return new GatewayRefund($refund['id'] ?? null, match ($refund['status'] ?? null) {
            'processed' => RefundStatus::Succeeded,
            'failed' => RefundStatus::Failed,
            default => RefundStatus::Pending,
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.razorpay.base_url'))
            ->withBasicAuth((string) config('services.razorpay.key_id'), (string) config('services.razorpay.key_secret'))
            ->asJson()
            ->acceptJson()
            ->timeout(15);
    }
}
