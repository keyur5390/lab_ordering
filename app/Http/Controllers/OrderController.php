<?php

namespace App\Http\Controllers;

use App\Http\Requests\RescheduleOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A customer's own orders.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        return Inertia::render('orders/index', [
            'orders' => OrderResource::collection($request->user()->orders()->withCount('items')->paginate(20)),
        ]);
    }

    public function show(Order $order, Settings $settings): Response
    {
        Gate::authorize('view', $order);

        return Inertia::render('orders/show', [
            'order' => OrderResource::make($order->load(['items', 'payments.refunds'])),
            'cutoffHours' => $settings->cancellationCutoffHours(),
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $message = match ($this->orders->cancel($order)) {
            null => 'Your order has been cancelled.',
            true => 'Your order has been cancelled. A full refund is on its way to your original payment method.',
            false => 'Your order has been cancelled. We couldn\'t send your refund automatically; our team will process it.',
        };

        return to_route('orders.show', $order)->with('status', $message);
    }

    public function editSlot(Order $order, SlotService $slots): Response
    {
        Gate::authorize('reschedule', $order);

        return Inertia::render('orders/reschedule', [
            'order' => OrderResource::make($order),
            'slots' => $order->schedule() ? $slots->days($order->schedule()) : [],
            'submitUrl' => route('orders.reschedule', $order),
            'backUrl' => route('orders.show', $order),
            'admin' => false,
        ]);
    }

    public function reschedule(RescheduleOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->reschedule($order, $request->slotStartsAt());

        return to_route('orders.show', $order)->with('status', 'Your collection has been rescheduled.');
    }

    /** Prescriptions are private: only the order's customer and admins can download them. */
    public function prescription(Order $order): StreamedResponse
    {
        Gate::authorize('view', $order);
        abort_unless($order->prescription_path, 404);

        return Storage::disk(OrderService::PRESCRIPTION_DISK)->download($order->prescription_path, $order->prescription_name);
    }
}
