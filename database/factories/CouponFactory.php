<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state: 10% off, no limits.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'is_active' => true,
        ];
    }

    public function amount(int $minor): static
    {
        return $this->state(fn (array $attributes) => ['discount_type' => DiscountType::Amount, 'discount_value' => $minor]);
    }
}
