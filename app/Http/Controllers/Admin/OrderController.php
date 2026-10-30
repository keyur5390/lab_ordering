<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CollectorService;
use App\Services\OrderService;
use App\Services\SlotService;
use App\Settings\Settings;
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

    public function show(Request $request, Order $order, CollectorService $collectors, Settings $settings): Response
    {
        return Inertia::render('admin/orders/show', [
            'order' => OrderResource::make($order->load(['items', 'user', 'payments.refunds.user', 'collector'])),
            // Collectors (client, 2026-10-30): who is assigned, who could be, and how the visit went.
            'collector' => [
                'assigned' => $order->collector?->only(['id', 'name']),
                'options' => $collectors->collectorsFor($order)->map->only(['id', 'name'])->values(),
                'can_assign' => $request->user()->can('assignCollector', $order) && $collectors->canAssign($order),
                'visit_failed' => $order->visit_failed_at ? [
                    'at' => $order->visit_failed_at->setTimezone($settings->timezone())->toIso8601String(),
                    'reason' => $order->visit_failure_reason?->label(),
                    'note' => $order->visit_failure_note,
                ] : null,
            ],
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $refunded = $this->orders->cancel($order, $request->user());
        $redirect = to_route('admin.orders.show', $order);

        return $refunded === false
            ? $redirect->withErrors(['amount' => 'Order cancelled, but the payment gateway did not accept the refund. Try the refund again below.'])
            : $redirect->with('status', $refunded ? 'Order cancelled and refunded in full.' : 'Order cancelled.');
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
