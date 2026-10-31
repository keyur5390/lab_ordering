<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_name' => Countries::name($this->country),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'map_url' => "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map=17/{$this->latitude}/{$this->longitude}",
            'phone' => $this->phone,
            'email' => $this->email,
            'directions' => $this->directions,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'is_active' => $this->is_active,
            'has_schedule' => $this->hasSchedule(),
        ];
    }
}
