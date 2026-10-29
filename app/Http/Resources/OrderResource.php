<?php

namespace App\Http\Resources;

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
            'can_pay' => (bool) $user?->can('pay', $this->resource),
            'can_refund' => $this->whenLoaded('payments', fn () => (bool) $user?->can('refund', $this->resource), false),
            'can_cancel' => (bool) $user?->can('cancel', $this->resource),
            'can_reschedule' => (bool) $user?->can('reschedule', $this->resource),
        ];
    }
}
