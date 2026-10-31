<?php

namespace App\Enums;

/**
 * Order lifecycle (PROJECT.md §8, assumed; TODO(client): confirm).
 * pending_payment → confirmed → collector_assigned → sample_collected → received_at_lab
 * → report_ready → completed, plus cancelled and refunded.
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case CollectorAssigned = 'collector_assigned';
    case SampleCollected = 'sample_collected';
    case ReceivedAtLab = 'received_at_lab';
    case ReportReady = 'report_ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Confirmed => 'Confirmed',
            self::CollectorAssigned => 'Collector assigned',
            self::SampleCollected => 'Sample collected',
            self::ReceivedAtLab => 'Received at lab',
            self::ReportReady => 'Report ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /** Before the sample is collected, an order can still be cancelled or rescheduled. */
    public function isBeforeCollection(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed, self::CollectorAssigned], true);
    }

    /** Takes up a place in its collection slot. */
    public function holdsSlot(): bool
    {
        return $this->isBeforeCollection();
    }

    /**
     * Statuses that hold a slot, for queries.
     *
     * @return list<string>
     */
    public static function holdingSlot(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->holdsSlot()),
        ));
    }
}
