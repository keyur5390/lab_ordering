<?php

namespace App\Http\Resources;

use App\Models\ServiceArea;
use App\Settings\Settings;
use App\Support\Countries;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceArea
 */
class ServiceAreaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'country' => $this->country,
            'country_name' => $this->country ? Countries::name($this->country) : null,
            'postcodes_count' => $this->whenCounted('postcodes'),
            'postcodes' => $this->whenLoaded('postcodes', fn () => $this->postcodes->pluck('postcode')),
            'center' => $this->center_latitude !== null ? [$this->center_latitude, $this->center_longitude] : null,
            'radius_km' => $this->radius_meters !== null ? $this->radius_meters / 1000 : null,
            'polygon' => $this->polygon,
            'is_active' => $this->is_active,
            // Decimal in the site currency, for the form (client, 2026-10-30: fee per area).
            'collection_fee' => Money::toDecimal($this->collection_fee_amount, app(Settings::class)->currency()),
            'collection_fee_amount' => $this->collection_fee_amount,
            'has_schedule' => $this->hasSchedule(),
        ];
    }
}
