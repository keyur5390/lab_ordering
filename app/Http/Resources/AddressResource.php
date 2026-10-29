<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Services\ServiceAreaService;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
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
            'label' => $this->label,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_name' => Countries::name($this->country),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            // Whether home collection is available here.
            'serviceable' => app(ServiceAreaService::class)->covers($this->resource),
        ];
    }
}
