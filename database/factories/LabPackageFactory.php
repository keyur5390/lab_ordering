<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\PackagePricingMode;
use App\Models\LabPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LabPackage>
 */
class LabPackageFactory extends Factory
{
    /**
     * Define the model's default state: a fixed-price package. Attach tests with hasAttached()/withTests().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true)).' Package';

        return [
            'name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('P-####')),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'pricing_mode' => PackagePricingMode::Fixed,
            'price_amount' => fake()->numberBetween(50, 500) * 100,
            'discount_type' => null,
            'discount_value' => null,
            'is_active' => true,
        ];
    }

    public function discounted(DiscountType $type, int $value): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PackagePricingMode::Discounted,
            'price_amount' => null,
            'discount_type' => $type,
            'discount_value' => $value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
