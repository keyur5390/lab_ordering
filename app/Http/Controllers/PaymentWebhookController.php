<?php

namespace App\Http\Controllers;

use App\Enums\PaymentGatewayName;
use App\Services\Payments\InvalidWebhookSignature;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Payment events from the gateways. Signed by the gateway; exempt from CSRF.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentService $payments): Response
    {
        $name = PaymentGatewayName::tryFrom($gateway) ?? abort(404);

        try {
            $payments->handleWebhook($name, $request);
        } catch (InvalidWebhookSignature) {
            return response('Invalid signature', 400);
        }

        return response('OK');
    }
}
