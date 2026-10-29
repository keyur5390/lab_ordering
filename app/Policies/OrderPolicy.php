<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;

/**
 * Customers see and change their own orders, within the cancellation cutoff (client, 2026-10-29).
 * Admins can see every order and cancel or reschedule any order before collection.
 */
class OrderPolicy
{
    public function __construct(private OrderService $orders) {}

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->change($user, $order);
    }

    public function reschedule(User $user, Order $order): bool
    {
        return $this->change($user, $order);
    }

    private function change(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return $order->status->isBeforeCollection();
        }

        return $order->user_id === $user->id && $this->orders->customerCanChange($order);
    }
}
