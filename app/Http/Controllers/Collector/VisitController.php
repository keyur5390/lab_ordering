<?php

namespace App\Http\Controllers\Collector;

use App\Enums\CollectionPaymentMethod;
use App\Enums\PaymentMethod;
use App\Enums\VisitFailureReason;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CollectorService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A collector's day (client, 2026-10-30): their visits and the unassigned ones they can take.
 */
class VisitController extends Controller
{
    public function __construct(
        private CollectorService $collectors,
        private Settings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $timezone = $this->settings->timezone();
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? null;
        $day = $date ? CarbonImmutable::parse($date, $timezone) : CarbonImmutable::now($timezone);
        $user = $request->user();

        return Inertia::render('collector/index', [
            'date' => $day->toDateString(),
            'today' => CarbonImmutable::now($timezone)->toDateString(),
            'timezone' => $timezone,
            'visits' => $this->collectors->visits($user, $day)->map(fn (Order $order) => $this->visit($order, true))->values(),
            'claimable' => $this->collectors->claimable($user, $day)->map(fn (Order $order) => $this->visit($order, false))->values(),
            'hasAreas' => $user->collectorAreas()->exists(),
            'failureReasons' => array_map(fn (VisitFailureReason $r) => ['value' => $r->value, 'label' => $r->label()], VisitFailureReason::cases()),
            'paymentMethods' => array_map(fn (CollectionPaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], CollectionPaymentMethod::cases()),
        ]);
    }

    public function claim(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('access-collector');
        $this->collectors->claim($order, $request->user());

        return $this->back($order, "You've taken {$order->number}.");
    }

    public function collected(Order $order): RedirectResponse
    {
        Gate::authorize('visit', $order);
        $this->collectors->markCollected($order);

        return $this->back($order, "{$order->number}: sample collected.");
    }

    public function failed(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('visit', $order);
        $validated = $request->validate([
            'reason' => ['required', Rule::enum(VisitFailureReason::class)],
            'note' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('reason') === VisitFailureReason::Other->value)],
        ]);

        $this->collectors->markFailed($order, VisitFailureReason::from($validated['reason']), $validated['note'] ?? null);

        return $this->back($order, "{$order->number}: visit marked as failed. The office has been told.");
    }

    public function payment(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('visit', $order);
        $validated = $request->validate(['method' => ['required', Rule::enum(CollectionPaymentMethod::class)]]);

        $this->collectors->recordPayment($order, CollectionPaymentMethod::from($validated['method']));

        return $this->back($order, "{$order->number}: payment recorded.");
    }

    private function back(Order $order, string $status): RedirectResponse
    {
        $date = $order->slot_starts_at->setTimezone($this->settings->timezone())->toDateString();

        return to_route('collector.index', ['date' => $date])->with('status', $status);
    }

    /**
     * What a collector needs at the door. Claimable visits show the area and slot, not who or where.
     *
     * @return array<string, mixed>
     */
    private function visit(Order $order, bool $mine): array
    {
        $timezone = $this->settings->timezone();
        $address = $order->address;

        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'slot_starts_at' => $order->slot_starts_at->setTimezone($timezone)->toIso8601String(),
            'slot_ends_at' => $order->slot_ends_at->setTimezone($timezone)->toIso8601String(),
            'area' => $order->serviceArea?->name,
            'patients_count' => $order->items->pluck('patient_name')->unique()->count(),
            ...($mine ? [
                'address' => $address,
                'map_url' => isset($address['latitude'], $address['longitude'])
                    ? "https://www.openstreetmap.org/?mlat={$address['latitude']}&mlon={$address['longitude']}#map=18/{$address['latitude']}/{$address['longitude']}"
                    : 'https://www.openstreetmap.org/search?query='.rawurlencode(collect([$address['line1'] ?? null, $address['city'] ?? null, $address['postcode'] ?? null])->filter()->implode(', ')),
                'phone' => $order->user->routeNotificationForSms() ?? $order->user->patients()->whereNotNull('phone')->value('phone'),
                'patients' => $order->items->groupBy('patient_name')->map(fn ($items, string $name) => [
                    'name' => $name,
                    'tests' => $items->map(fn (OrderItem $item) => $item->name)->values(),
                ])->values(),
                'pay_at_collection' => $order->payment_method === PaymentMethod::PayAtCollection,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'payment_collected' => $order->payment_collected_at !== null,
                'visit_failed' => $order->visit_failed_at !== null,
                'visit_failure' => $order->visit_failure_reason?->label(),
            ] : []),
        ];
    }
}
