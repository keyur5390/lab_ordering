<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SlotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * All orders, for admins. Moving orders through collection and lab statuses waits on the
 * collector and lab staff roles (PROJECT.md §2, OPEN).
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)]])['status'] ?? null;

        return Inertia::render('admin/orders/index', [
            'orders' => OrderResource::collection(
                Order::with('user')->withCount('items')
                    ->when($status, fn ($query) => $query->where('status', $status))
                    ->orderBy('slot_starts_at')
                    ->paginate(25)
                    ->withQueryString(),
            ),
            'status' => $status,
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/show', [
            'order' => OrderResource::make($order->load(['items', 'user'])),
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $this->orders->cancel($order);

        return to_route('admin.orders.show', $order)->with('status', 'Order cancelled.');
    }

    public function editSlot(Order $order, SlotService $slots): Response
    {
        Gate::authorize('reschedule', $order);

        return Inertia::render('orders/reschedule', [
            'order' => OrderResource::make($order),
            'slots' => $order->serviceArea ? $slots->days($order->serviceArea) : [],
            'submitUrl' => route('admin.orders.reschedule', $order),
            'backUrl' => route('admin.orders.show', $order),
            'admin' => true,
        ]);
    }

    public function reschedule(RescheduleOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->reschedule($order, $request->slotStartsAt());

        return to_route('admin.orders.show', $order)->with('status', 'Order rescheduled.');
    }
}
