<?php

namespace Database\Factories;

use App\Models\LabTest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LabTest>
 */
class LabTestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('T-####')),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'sample_type' => fake()->randomElement(['Blood', 'Urine', 'Saliva']),
            'preparation' => null,
            'requires_prescription' => false,
            'turnaround_hours' => fake()->randomElement([6, 12, 24, 48]),
            'price_amount' => fake()->numberBetween(20, 300) * 100,
            'is_active' => true,
        ];
    }

    public function prescriptionRequired(): static
    {
        return $this->state(fn (array $attributes) => ['requires_prescription' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
