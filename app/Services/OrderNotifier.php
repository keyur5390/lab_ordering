<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Notifications\OrderNotification;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what (client, 2026-10-29). Customers get the order events; admin alerts go by
 * email to the address in Settings. Wording, channels and opt-outs are handled by OrderNotification.
 */
class OrderNotifier
{
    public function __construct(private Settings $settings) {}

    /** Pay-at-collection orders are confirmed when placed; online ones when paid (paymentReceived). */
    public function placed(Order $order): void
    {
        if ($order->status === OrderStatus::Confirmed) {
            $this->send(NotificationEvent::OrderConfirmed, $order);
            $this->send(NotificationEvent::AdminNewOrder, $order);
        }
    }

    public function paymentReceived(Order $order, int $amount): void
    {
        $this->send(NotificationEvent::PaymentReceived, $order, $amount);
        $this->send(NotificationEvent::AdminNewOrder, $order);
    }

    public function refunded(Order $order, Refund $refund): void
    {
        $refund->status->counts()
            ? $this->send(NotificationEvent::RefundIssued, $order, $refund->amount)
            : $this->send(NotificationEvent::AdminRefundFailed, $order, $refund->amount);
    }

    public function cancelled(Order $order): void
    {
        $this->send(NotificationEvent::OrderCancelled, $order);
        $this->send(NotificationEvent::AdminOrderCancelled, $order);
    }

    public function paymentExpired(Order $order): void
    {
        $this->send(NotificationEvent::PaymentExpired, $order);
    }

    public function rescheduled(Order $order): void
    {
        $this->send(NotificationEvent::OrderRescheduled, $order);
    }

    public function reportReady(Order $order): void
    {
        $this->send(NotificationEvent::ReportReady, $order);
    }

    /**
     * Sends the collection reminder for orders whose slot starts within the admin-set number of
     * hours. Orders booked after their reminder time has passed don't get one. Returns how many.
     */
    public function sendDueReminders(?CarbonImmutable $now = null): int
    {
        $hours = $this->settings->reminderHoursBefore();
        if ($hours < 1) {
            return 0;
        }

        $now = ($now ?? CarbonImmutable::now())->utc();
        $orders = Order::with('user')
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::CollectorAssigned])
            ->whereNull('reminder_sent_at')
            ->whereBetween('slot_starts_at', [$now, $now->addHours($hours)])
            ->get()
            ->filter(fn (Order $order) => $order->created_at->lte($order->slot_starts_at->copy()->subHours($hours)));

        foreach ($orders as $order) {
            $order->update(['reminder_sent_at' => $now]);
            $this->send(NotificationEvent::CollectionReminder, $order);
        }

        return $orders->count();
    }

    private function send(NotificationEvent $event, Order $order, ?int $amount = null): void
    {
        $notification = new OrderNotification($event, $order, $amount);

        if (! $event->forAdmins()) {
            $order->user->notify($notification);
        } elseif ($email = $this->settings->adminNotificationEmail()) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
