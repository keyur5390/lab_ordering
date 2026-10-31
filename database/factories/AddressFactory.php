<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state: an address without a map pin.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement([null, 'Home', 'Work']),
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'region' => null,
            'postcode' => fake()->postcode(),
            'country' => 'GB',
            'latitude' => null,
            'longitude' => null,
        ];
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => ['latitude' => $latitude, 'longitude' => $longitude]);
    }
}
