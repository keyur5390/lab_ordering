<?php

namespace App\Http\Resources;

use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LabTest
 */
class LabTestResource extends JsonResource
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
            'code' => $this->code,
            'slug' => $this->slug,
            'description' => $this->description,
            'sample_type' => $this->sample_type,
            'preparation' => $this->preparation,
            'turnaround_hours' => $this->turnaround_hours,
            'price_amount' => $this->price_amount,
            'is_active' => $this->is_active,
        ];
    }
}
