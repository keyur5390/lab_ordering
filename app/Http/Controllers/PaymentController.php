<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayException;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paying an online order at the gateway, and coming back from it.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** Sends the customer to a new hosted checkout (first try or retry). */
    public function store(Order $order): Response
    {
        Gate::authorize('pay', $order);

        try {
            return Inertia::location($this->payments->start($order));
        } catch (PaymentGatewayException) {
            return to_route('orders.show', $order)
                ->withErrors(['payment' => 'We couldn\'t open the payment page. Please try again.']);
        }
    }

    /** The gateway sends the customer here after paying. */
    public function return(Request $request, Order $order, Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $order);

        $redirect = to_route('orders.show', $order);

        return $this->payments->handleReturn($payment, $request)
            ? $redirect->with('status', 'Thank you! Your payment was received and your order is confirmed.')
            : $redirect->with('status', 'We\'re waiting for the payment to be confirmed. This page will show it once it is.');
    }
}
