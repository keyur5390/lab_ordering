<?php

namespace App\Http\Resources;

use App\Enums\CollectionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\PaymentService;
use App\Settings\Settings;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Times are given in the site timezone. `can_cancel` / `can_reschedule` are for the viewer.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(Settings::class)->timezone();
        $user = $request->user();

        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'collection_type' => $this->collection_type->value,
            'collection_type_label' => $this->collection_type->label(),
            // Lab visits (B-17): how to find and contact the branch; detail pages only.
            'branch' => $this->whenLoaded('items', fn () => $this->collection_type === CollectionType::LabVisit && $this->branch
                ? $this->branch->only(['name', 'phone', 'email', 'directions']) + [
                    'map_url' => "https://www.openstreetmap.org/?mlat={$this->branch->latitude}&mlon={$this->branch->longitude}#map=17/{$this->branch->latitude}/{$this->branch->longitude}",
                ]
                : null),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'payment_expires_at' => $this->payment_expires_at?->setTimezone($timezone)->toIso8601String(),
            'slot_starts_at' => $this->slot_starts_at->setTimezone($timezone)->toIso8601String(),
            'slot_ends_at' => $this->slot_ends_at->setTimezone($timezone)->toIso8601String(),
            'placed_at' => $this->created_at->setTimezone($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'address' => [...$this->address, 'country_name' => Countries::name($this->address['country'] ?? '')],
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'total_amount' => $this->total_amount,
            // As charged (client, 2026-10-30): coupon, collection fee and tax.
            'coupon_code' => $this->coupon_code,
            'discount_amount' => $this->discount_amount,
            'collection_fee_amount' => $this->collection_fee_amount,
            'tax_rate_percent' => $this->tax_rate_basis_points !== null ? $this->tax_rate_basis_points / 100 : null,
            'tax_inclusive' => $this->tax_inclusive,
            'tax_amount' => $this->tax_amount,
            'items_count' => $this->whenCounted('items'),
            'customer' => $this->whenLoaded('user', fn () => ['name' => $this->user->name, 'email' => $this->user->email]),
            // Grouped by patient for display.
            'patients' => $this->whenLoaded('items', fn () => $this->items->groupBy(
                fn (OrderItem $item) => $item->patient_id ?? "{$item->patient_name}|{$item->patient_date_of_birth->toDateString()}",
            )->map(fn ($items) => [
                'name' => $items->first()->patient_name,
                'date_of_birth' => $items->first()->patient_date_of_birth->toDateString(),
                'sex' => $items->first()->patient_sex,
                'items' => $items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'type' => $item->item_type->value,
                    'name' => $item->name,
                    'code' => $item->code,
                    'test_names' => $item->test_names,
                    'price_amount' => $item->price_amount,
                    // Only served to people allowed to see the order; the download checks again.
                    'report_url' => $item->report_path ? route('orders.reports.show', [$this->resource, $item]) : null,
                ])->values(),
            ])->values()),
            'has_prescription' => $this->prescription_path !== null,
            'prescription_name' => $this->prescription_name,
            'payment' => $this->whenLoaded('payments', fn () => [
                ...app(PaymentService::class)->amounts($this->resource),
                'refunds' => $this->payments->flatMap(fn (Payment $payment) => $payment->refunds->map(fn (Refund $refund) => [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status->value,
                    'status_label' => $refund->status->label(),
                    'reason' => $refund->reason,
                    'by' => $refund->relationLoaded('user') ? $refund->user?->name : null,
                    'created_at' => $refund->created_at->setTimezone($timezone)->toIso8601String(),
                ]))->values(),
            ]),
            // Customers see the collector's first name (client, 2026-10-30).
            'collector_first_name' => $this->collector ? (string) str($this->collector->name)->before(' ') : null,
            'payment_collected' => $this->payment_collected_at ? [
                'at' => $this->payment_collected_at->setTimezone($timezone)->toIso8601String(),
                'method' => $this->payment_collected_method?->label(),
            ] : null,
            'can_pay' => (bool) $user?->can('pay', $this->resource),
            'can_refund' => $this->whenLoaded('payments', fn () => (bool) $user?->can('refund', $this->resource), false),
            'can_cancel' => (bool) $user?->can('cancel', $this->resource),
            'can_reschedule' => (bool) $user?->can('reschedule', $this->resource),
        ];
    }
}
