<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Customers see and change their own orders, within the cancellation cutoff (client, 2026-10-29).
 * Admins can see every order and cancel or reschedule any order before collection, and refund
 * online payments. Lab staff can see every order (lab work is checked in LabService).
 */
class OrderPolicy
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
    ) {}

    /** Lab staff read every order (client, 2026-10-29). */
    public function view(User $user, Order $order): bool
    {
        return $user->isLabStaff() || $order->user_id === $user->id;
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

    /** Admins assign collectors (client, 2026-10-30). */
    public function assignCollector(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /** Lab staff and admins record pay-at-collection payments (B-21); collectors use their own screen. */
    public function recordPayment(User $user, Order $order): bool
    {
        return $user->isLabStaff();
    }

    /** The assigned collector works on the visit. */
    public function visit(User $user, Order $order): bool
    {
        return $user->isCollector() && $order->collector_id === $user->id;
    }

    private function change(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return $order->status->isBeforeCollection();
        }

        return $order->user_id === $user->id && $this->orders->customerCanChange($order);
    }
}
