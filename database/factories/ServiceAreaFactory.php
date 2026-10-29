<?php

namespace Database\Factories;

use App\Enums\ServiceAreaType;
use App\Models\ServiceArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceArea>
 */
class ServiceAreaFactory extends Factory
{
    /**
     * Define the model's default state: a postcode area (add postcodes via ServiceAreaService::syncPostcodes()).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' area',
            'type' => ServiceAreaType::Postcodes,
            'country' => 'GB',
            'is_active' => true,
        ];
    }

    public function radius(float $latitude, float $longitude, int $meters): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceAreaType::Radius,
            'country' => null,
            'center_latitude' => $latitude,
            'center_longitude' => $longitude,
            'radius_meters' => $meters,
        ]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public function polygon(array $points): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceAreaType::Polygon,
            'country' => null,
            'polygon' => $points,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
