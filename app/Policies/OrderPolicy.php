<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Customers see and change their own orders, within the cancellation cutoff (client, 2026-10-29).
 * Admins can see every order and cancel or reschedule any order before collection, and refund
 * online payments.
 */
class OrderPolicy
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
    ) {}

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

    /** Only the customer pays, while the order is unpaid and its hold lasts. */
    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $this->payments->canPay($order);
    }

    /** Admins can refund whatever was paid online and not yet refunded (client, 2026-10-29). */
    public function refund(User $user, Order $order): bool
    {
        return $user->isAdmin() && $this->payments->amounts($order)['refundable'] > 0;
    }

    private function change(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return $order->status->isBeforeCollection();
        }

        return $order->user_id === $user->id && $this->orders->customerCanChange($order);
    }
}
