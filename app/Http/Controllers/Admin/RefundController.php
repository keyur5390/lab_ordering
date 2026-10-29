<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundOrderRequest;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;

/**
 * Full or partial refunds of online payments, by admins (client, 2026-10-29).
 */
class RefundController extends Controller
{
    public function store(RefundOrderRequest $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $refunded = $payments->refund($order, $request->amountMinor(), $request->user(), $request->validated('reason'));

        return $refunded
            ? to_route('admin.orders.show', $order)->with('status', 'Refund sent.')
            : to_route('admin.orders.show', $order)->withErrors(['amount' => 'The payment gateway did not accept the refund. Please try again.']);
    }
}
