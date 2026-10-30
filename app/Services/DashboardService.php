<?php

namespace App\Services;

use App\Enums\CollectionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ServiceArea;
use App\Settings\Settings;
use Carbon\CarbonImmutable;

/**
 * The admin dashboard's numbers (client, 2026-10-30): today's work, orders by status, money for a
 * period, and things that need attention. Days and periods follow the site timezone.
 */
class DashboardService
{
    /** Periods for the money tiles: key => days back (today counts as one). */
    public const PERIODS = ['today' => 1, '7d' => 7, '30d' => 30];

    /** Unpaid online orders whose hold ends within this many minutes are flagged. */
    private const EXPIRING_SOON_MINUTES = 10;

    public function __construct(
        private PaymentService $payments,
        private Settings $settings,
    ) {}

    /**
     * @return array<string, int>
     */
    public function today(?CarbonImmutable $now = null): array
    {
        [$start, $end] = $this->days(1, $now);
        $tomorrowEnd = $end->addDay();
        $booked = [OrderStatus::Confirmed, OrderStatus::CollectorAssigned, OrderStatus::SampleCollected, OrderStatus::ReceivedAtLab, OrderStatus::ReportReady, OrderStatus::Completed];
        $todays = fn (CollectionType $type) => Order::where('collection_type', $type)->whereIn('status', $booked)
            ->whereBetween('slot_starts_at', [$start, $end])->count();

        return [
            'home_collections' => $todays(CollectionType::Home),
            'lab_visits' => $todays(CollectionType::LabVisit),
            // Home visits today or tomorrow that nobody has taken yet.
            'unassigned' => Order::where('collection_type', CollectionType::Home)->where('status', OrderStatus::Confirmed)
                ->whereNull('collector_id')->whereBetween('slot_starts_at', [$start, $tomorrowEnd])->count(),
            'failed_visits' => Order::where('status', OrderStatus::CollectorAssigned)->whereNotNull('visit_failed_at')->count(),
            'awaiting_reports' => Order::where('status', OrderStatus::ReceivedAtLab)->count(),
        ];
    }

    /**
     * Orders per status, every status listed.
     *
     * @return list<array{status: string, label: string, count: int}>
     */
    public function statusCounts(): array
    {
        $counts = Order::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ], OrderStatus::cases());
    }

    /**
     * Money in the site currency over the last N days: paid online, paid at collection, refunded.
     *
     * @return array{currency: string|null, paid_online: int, paid_at_collection: int, refunded: int, net: int}
     */
    public function money(string $period, ?CarbonImmutable $now = null): array
    {
        $currency = $this->settings->currency()?->value;
        [$start, $end] = $this->days(self::PERIODS[$period] ?? 1, $now);

        $online = (int) Payment::where('status', PaymentStatus::Paid)->where('currency', $currency)
            ->whereBetween('paid_at', [$start, $end])->sum('amount');
        $atCollection = (int) Order::where('currency', $currency)->whereBetween('payment_collected_at', [$start, $end])->sum('total_amount');
        $refunded = (int) Refund::whereIn('status', [RefundStatus::Pending, RefundStatus::Succeeded])
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('payment', fn ($query) => $query->where('currency', $currency))
            ->sum('amount');

        return [
            'currency' => $currency,
            'paid_online' => $online,
            'paid_at_collection' => $atCollection,
            'refunded' => $refunded,
            'net' => $online + $atCollection - $refunded,
        ];
    }

    /**
     * Things that need an admin, most serious first. Each has a tone, a message and a link.
     *
     * @return list<array{tone: string, message: string, url: string}>
     */
    public function alerts(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $alerts = [];

        if (! $this->settings->currency()) {
            $alerts[] = ['tone' => 'danger', 'message' => 'No currency is set, so customers can\'t order.', 'url' => route('admin.settings.edit')];
        }

        $bookable = ServiceArea::where('is_active', true)->get()->contains->hasSchedule()
            || Branch::where('is_active', true)->get()->contains->hasSchedule();
        if (! $bookable) {
            $alerts[] = ['tone' => 'danger', 'message' => 'No published service area or branch has a schedule, so there are no slots to book.', 'url' => route('admin.service-areas.index')];
        }

        $failedRefunds = Order::whereHas('payments.refunds', fn ($query) => $query->where('status', RefundStatus::Failed))
            ->get()->filter(fn (Order $order) => $this->payments->amounts($order)['refundable'] > 0 && $order->status === OrderStatus::Cancelled)
            ->count();
        if ($failedRefunds > 0) {
            $alerts[] = ['tone' => 'danger', 'message' => "{$failedRefunds} cancelled ".str('order')->plural($failedRefunds).' still '.($failedRefunds === 1 ? 'needs' : 'need').' a refund: the gateway rejected it.', 'url' => route('admin.orders.index', ['status' => OrderStatus::Cancelled->value])];
        }

        $failedVisits = $this->today($now)['failed_visits'];
        if ($failedVisits > 0) {
            $alerts[] = ['tone' => 'warning', 'message' => "{$failedVisits} home ".str('visit')->plural($failedVisits).' failed and '.($failedVisits === 1 ? 'needs' : 'need').' rescheduling or cancelling.', 'url' => route('admin.orders.index', ['status' => OrderStatus::CollectorAssigned->value])];
        }

        $expiring = Order::where('status', OrderStatus::PendingPayment)
            ->whereBetween('payment_expires_at', [$now->utc(), $now->utc()->addMinutes(self::EXPIRING_SOON_MINUTES)])->count();
        if ($expiring > 0) {
            $alerts[] = ['tone' => 'warning', 'message' => "{$expiring} unpaid ".str('order')->plural($expiring).' will be cancelled within '.self::EXPIRING_SOON_MINUTES.' minutes.', 'url' => route('admin.orders.index', ['status' => OrderStatus::PendingPayment->value])];
        }

        if (! $this->settings->adminNotificationEmail()) {
            $alerts[] = ['tone' => 'info', 'message' => 'No admin alert email is set, so you won\'t be emailed about new orders or problems.', 'url' => route('admin.settings.edit')];
        }
        if ($this->settings->cancellationCutoffHours() === null) {
            $alerts[] = ['tone' => 'info', 'message' => 'No cancellation cutoff is set, so only admins can cancel or reschedule orders.', 'url' => route('admin.settings.edit')];
        }

        return $alerts;
    }

    /**
     * The last $count days in the site timezone, ending at the end of today, as UTC bounds.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function days(int $count, ?CarbonImmutable $now): array
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone($this->settings->timezone());

        return [$today->startOfDay()->subDays($count - 1)->utc(), $today->endOfDay()->utc()];
    }
}
