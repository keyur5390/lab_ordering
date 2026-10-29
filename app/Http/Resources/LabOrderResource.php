<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order in the lab list: no customer, address or payment details (client, 2026-10-29).
 *
 * @mixin Order
 */
class LabOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(Settings::class)->timezone();

        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'slot_starts_at' => $this->slot_starts_at->setTimezone($timezone)->toIso8601String(),
            'slot_ends_at' => $this->slot_ends_at->setTimezone($timezone)->toIso8601String(),
            'items_count' => $this->items_count,
            'reports_count' => $this->reports_count,
        ];
    }
}
