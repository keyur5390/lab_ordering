<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
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
            'payment_method_label' => $this->payment_method->label(),
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
                    'type' => $item->item_type->value,
                    'name' => $item->name,
                    'code' => $item->code,
                    'test_names' => $item->test_names,
                    'price_amount' => $item->price_amount,
                ])->values(),
            ])->values()),
            'has_prescription' => $this->prescription_path !== null,
            'prescription_name' => $this->prescription_name,
            'can_cancel' => (bool) $user?->can('cancel', $this->resource),
            'can_reschedule' => (bool) $user?->can('reschedule', $this->resource),
        ];
    }
}
