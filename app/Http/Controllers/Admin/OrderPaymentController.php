<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CollectionPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Staff record a pay-at-collection payment, e.g. at the branch for a lab visit (client, 2026-10-31).
 */
class OrderPaymentController extends Controller
{
    public function store(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('recordPayment', $order);
        $method = $request->validate(['method' => ['required', Rule::enum(CollectionPaymentMethod::class)]])['method'];

        $payments->recordCollectedPayment($order, CollectionPaymentMethod::from($method));

        return to_route('admin.orders.show', $order)->with('status', 'Payment recorded and invoice issued.');
    }
}
