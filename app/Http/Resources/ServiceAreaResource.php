<?php

namespace App\Http\Resources;

use App\Models\ServiceArea;
use App\Support\Countries;
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
        ];
    }
}
