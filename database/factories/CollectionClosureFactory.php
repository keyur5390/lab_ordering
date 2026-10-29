<?php

namespace Database\Factories;

use App\Models\CollectionClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionClosure>
 */
class CollectionClosureFactory extends Factory
{
    /**
     * Define the model's default state: a whole-day closure for all areas, tomorrow.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->addDay()->toDateString();

        return [
            'service_area_id' => null,
            'starts_on' => $date,
            'ends_on' => $date,
            'starts_at' => null,
            'ends_at' => null,
            'reason' => 'Holiday',
        ];
    }
}
