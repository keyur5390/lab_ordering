<?php

namespace App\Http\Resources;

use App\Models\LabPackage;
use App\Services\PackagePricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `tests` to be eager-loaded: the price and turnaround are derived from them.
 *
 * @mixin LabPackage
 */
class LabPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = app(PackagePricingService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'description' => $this->description,
            'pricing_mode' => $this->pricing_mode->value,
            'fixed_price_amount' => $this->price_amount,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            // What the customer pays, and what the tests would cost separately.
            'price_amount' => $pricing->price($this->resource),
            'tests_total_amount' => $pricing->testsTotal($this->resource),
            'turnaround_hours' => $pricing->turnaroundHours($this->resource),
            'is_active' => $this->is_active,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map->only(['id', 'name', 'slug'])->values()),
            'tests_count' => $this->tests->count(),
            'tests' => LabTestResource::collection($this->whenLoaded('tests')),
        ];
    }
}
