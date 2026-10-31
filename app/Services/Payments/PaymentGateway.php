<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * An online payment gateway with a hosted checkout page. Drivers talk to the gateway's HTTP API
 * directly; amounts are in minor units, as stored.
 */
interface PaymentGateway
{
    /**
     * Creates the hosted checkout the customer is sent to.
     *
     * @throws PaymentGatewayException
     */
    public function createCheckout(Payment $payment, Order $order, string $returnUrl, string $cancelUrl): CheckoutSession;

    /**
     * When the customer comes back from the checkout: the gateway's payment id if it was paid, else null.
     *
     * @throws PaymentGatewayException
     */
    public function paidOnReturn(Payment $payment, Request $request): ?string;

    /**
     * A paid checkout reported by a webhook, or null for events we don't act on.
     *
     * @throws InvalidWebhookSignature
     */
    public function paidFromWebhook(Request $request): ?PaidCheckout;

    /**
     * @throws PaymentGatewayException
     */
    public function refund(Payment $payment, int $amount): GatewayRefund;
}
