<?php

namespace App\Enums;

/**
 * What we notify about (client, 2026-10-29). Wording is per event and channel in
 * notification_templates, edited in Admin → Notifications.
 */
enum NotificationEvent: string
{
    /** A pay-at-collection order is placed. */
    case OrderConfirmed = 'order_confirmed';
    /** An online payment arrives and confirms the order. */
    case PaymentReceived = 'payment_received';
    case RefundIssued = 'refund_issued';
    /** Cancelled by the customer or an admin. */
    case OrderCancelled = 'order_cancelled';
    /** An unpaid online order's hold ran out and it was cancelled. */
    case PaymentExpired = 'payment_expired';
    case OrderRescheduled = 'order_rescheduled';
    case CollectionReminder = 'collection_reminder';
    /** All reports for the order are uploaded. */
    case ReportReady = 'report_ready';
    /** A collector is assigned (or changed) for the home visit. */
    case CollectorAssigned = 'collector_assigned';

    case AdminNewOrder = 'admin_new_order';
    case AdminOrderCancelled = 'admin_order_cancelled';
    case AdminRefundFailed = 'admin_refund_failed';
    case AdminVisitFailed = 'admin_visit_failed';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Order confirmed (pay at collection)',
            self::PaymentReceived => 'Payment received (order confirmed)',
            self::RefundIssued => 'Refund issued',
            self::OrderCancelled => 'Order cancelled',
            self::PaymentExpired => 'Payment time ran out',
            self::OrderRescheduled => 'Collection rescheduled',
            self::CollectionReminder => 'Collection reminder',
            self::ReportReady => 'Reports ready',
            self::CollectorAssigned => 'Collector assigned',
            self::AdminNewOrder => 'New order',
            self::AdminOrderCancelled => 'Order cancelled',
            self::AdminRefundFailed => 'Refund failed',
            self::AdminVisitFailed => 'Home visit failed',
        };
    }

    /** Admin alerts go by email to the address in Settings; the rest go to the customer. */
    public function forAdmins(): bool
    {
        return in_array($this, [self::AdminNewOrder, self::AdminOrderCancelled, self::AdminRefundFailed, self::AdminVisitFailed], true);
    }

    /**
     * @return list<NotificationChannel>
     */
    public function channels(): array
    {
        return $this->forAdmins() ? [NotificationChannel::Mail] : NotificationChannel::cases();
    }

    /**
     * Placeholders the wording can use, e.g. {order_number}.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        $common = ['site_name', 'customer_name', 'order_number', 'slot', 'address', 'total', 'order_url'];

        return match ($this) {
            self::PaymentReceived, self::RefundIssued, self::AdminRefundFailed => [...$common, 'amount'],
            self::CollectorAssigned => [...$common, 'collector_name'],
            self::AdminVisitFailed => [...$common, 'collector_name', 'reason'],
            default => $common,
        };
    }
}
