<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\CollectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admins assign, change or remove an order's collector (client, 2026-10-30).
 */
class OrderCollectorController extends Controller
{
    public function update(Request $request, Order $order, CollectorService $collectors): RedirectResponse
    {
        Gate::authorize('assignCollector', $order);
        $validated = $request->validate([
            'collector_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Collector->value)],
        ]);

        if ($validated['collector_id'] ?? null) {
            $collectors->assign($order, User::findOrFail($validated['collector_id']));
            $status = 'Collector assigned. The customer has been told.';
        } else {
            $collectors->unassign($order);
            $status = 'Collector removed.';
        }

        return to_route('admin.orders.show', $order)->with('status', $status);
    }
}
