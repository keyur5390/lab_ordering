<?php

namespace App\Services;

use App\Enums\CollectionPaymentMethod;
use App\Enums\CollectionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\VisitFailureReason;
use App\Models\Order;
use App\Models\User;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Home visits by collectors (client, 2026-10-30). Admins assign a collector from the order's
 * service area, and collectors can claim unassigned visits in their areas. Collectors mark the
 * sample collected, record a failed visit, and take payment for pay-at-collection orders.
 */
class CollectorService
{
    public function __construct(
        private LabService $lab,
        private OrderNotifier $notifier,
        private Settings $settings,
    ) {}

    /** A collector can be (re)assigned to a home visit until the sample is collected. */
    public function canAssign(Order $order): bool
    {
        return $order->collection_type === CollectionType::Home
            && in_array($order->status, [OrderStatus::Confirmed, OrderStatus::CollectorAssigned], true);
    }

    /**
     * Collectors who work in the order's service area.
     *
     * @return Collection<int, User>
     */
    public function collectorsFor(Order $order): Collection
    {
        return $order->serviceArea?->collectors()->orderBy('name')->get() ?? new Collection;
    }

    public function worksIn(User $collector, Order $order): bool
    {
        return $collector->isCollector()
            && $order->service_area_id !== null
            && $collector->collectorAreas()->whereKey($order->service_area_id)->exists();
    }

    /**
     * Assigns (or changes) the collector. The customer is told who is coming.
     *
     * @throws ValidationException
     */
    public function assign(Order $order, User $collector): void
    {
        if (! $this->worksIn($collector, $order)) {
            throw ValidationException::withMessages(['collector_id' => 'Choose a collector who works in this order\'s area.']);
        }

        $changed = DB::transaction(function () use ($order, $collector) {
            $locked = $this->lock($order);
            if (! $this->canAssign($locked)) {
                throw ValidationException::withMessages(['collector_id' => 'A collector can only be assigned before the sample is collected.']);
            }
            if ($locked->collector_id === $collector->id) {
                return false;
            }

            $locked->update([
                'status' => OrderStatus::CollectorAssigned,
                'collector_id' => $collector->id,
                'collector_assigned_at' => now(),
            ]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($changed) {
            $this->notifier->collectorAssigned($order->unsetRelation('collector'));
        }
    }

    /**
     * @throws ValidationException
     */
    public function unassign(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = $this->lock($order);
            if ($locked->status !== OrderStatus::CollectorAssigned) {
                throw ValidationException::withMessages(['collector_id' => 'This order has no collector to remove.']);
            }

            $locked->update(['status' => OrderStatus::Confirmed, 'collector_id' => null, 'collector_assigned_at' => null]);
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * A collector takes an unassigned visit in one of their areas. Locked so two collectors
     * can't both take it.
     *
     * @throws ValidationException
     */
    public function claim(Order $order, User $collector): void
    {
        if (! $this->worksIn($collector, $order)) {
            throw ValidationException::withMessages(['order' => 'This visit is outside your areas.']);
        }

        DB::transaction(function () use ($order, $collector) {
            $locked = $this->lock($order);
            if ($locked->status !== OrderStatus::Confirmed || $locked->collector_id !== null) {
                throw ValidationException::withMessages(['order' => 'Someone else has already taken this visit.']);
            }

            $this->assign($locked, $collector);
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * @throws ValidationException
     */
    public function markCollected(Order $order): void
    {
        $this->lab->markCollected($order);
    }

    /**
     * Records that the visit didn't work out; admins are alerted to reschedule or cancel.
     *
     * @throws ValidationException
     */
    public function markFailed(Order $order, VisitFailureReason $reason, ?string $note): void
    {
        if ($order->status !== OrderStatus::CollectorAssigned) {
            throw ValidationException::withMessages(['reason' => 'Only a visit that is still to be done can be marked as failed.']);
        }

        $order->update(['visit_failed_at' => now(), 'visit_failure_reason' => $reason, 'visit_failure_note' => $note]);
        $this->notifier->visitFailed($order);
    }

    /**
     * Pay-at-collection orders: the collector took the full amount.
     *
     * @throws ValidationException
     */
    public function recordPayment(Order $order, CollectionPaymentMethod $method): void
    {
        if ($order->payment_method !== PaymentMethod::PayAtCollection || $order->payment_collected_at !== null
            || ! in_array($order->status, [OrderStatus::CollectorAssigned, OrderStatus::SampleCollected], true)) {
            throw ValidationException::withMessages(['method' => 'There is no payment to collect for this order.']);
        }

        $order->update(['payment_collected_at' => now(), 'payment_collected_method' => $method]);
    }

    /**
     * The collector's visits on a day (site timezone), done or not, in slot order.
     *
     * @return Collection<int, Order>
     */
    public function visits(User $collector, CarbonImmutable $day): Collection
    {
        return $this->onDay(Order::where('collector_id', $collector->id), $day)
            ->whereIn('status', [OrderStatus::CollectorAssigned, OrderStatus::SampleCollected])
            ->get();
    }

    /**
     * Unassigned visits in the collector's areas on a day.
     *
     * @return Collection<int, Order>
     */
    public function claimable(User $collector, CarbonImmutable $day): Collection
    {
        return $this->onDay(Order::query(), $day)
            ->where('status', OrderStatus::Confirmed)
            ->whereNull('collector_id')
            ->whereIn('service_area_id', $collector->collectorAreas()->pluck('service_areas.id'))
            ->get();
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function onDay($query, CarbonImmutable $day)
    {
        $start = $day->setTimezone($this->settings->timezone())->startOfDay();

        return $query->with(['items', 'user', 'serviceArea'])
            ->whereBetween('slot_starts_at', [$start->utc(), $start->endOfDay()->utc()])
            ->orderBy('slot_starts_at');
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}
