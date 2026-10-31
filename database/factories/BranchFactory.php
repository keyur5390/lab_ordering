<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state: an active branch without a schedule.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Lab',
            'line1' => fake()->streetAddress(),
            'city' => 'Pune',
            'postcode' => '411001',
            'country' => 'IN',
            'latitude' => 18.5204,
            'longitude' => 73.8567,
            'phone' => '+91 20 5555 0000',
            'is_active' => true,
        ];
    }

    /** Same shape as ServiceAreaFactory::scheduled(): every day, $opens–$closes. */
    public function scheduled(int $slotMinutes = 30, int $capacity = 4, int $leadMinutes = 0, int $windowDays = 7, string $opens = '08:00', string $closes = '12:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_minutes' => $slotMinutes,
            'capacity_per_slot' => $capacity,
            'lead_time_minutes' => $leadMinutes,
            'booking_window_days' => $windowDays,
        ])->afterCreating(function (Branch $branch) use ($opens, $closes) {
            foreach (range(1, 7) as $weekday) {
                $branch->hours()->create(['weekday' => $weekday, 'opens_at' => $opens, 'closes_at' => $closes]);
            }
        });
    }
}
