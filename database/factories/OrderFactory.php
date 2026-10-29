<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state: a confirmed order two days from now, without items.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->utc()->addDays(2)->setTime(8, 0);

        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Confirmed,
            'payment_method' => PaymentMethod::PayAtCollection,
            'service_area_id' => ServiceArea::factory(),
            'slot_starts_at' => $start,
            'slot_ends_at' => $start->copy()->addHour(),
            'address' => ['label' => null, 'line1' => '1 Test Street', 'line2' => null, 'city' => 'London', 'region' => null, 'postcode' => 'SW1A 1AA', 'country' => 'GB'],
            'currency' => 'INR',
            'subtotal_amount' => 50000,
            'total_amount' => 50000,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Order $order) => $order->number ?? $order->update(['number' => sprintf('ORD-%06d', $order->id)]));
    }

    public function at(\DateTimeInterface $startsAt, int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_starts_at' => $startsAt,
            'slot_ends_at' => (clone $startsAt)->modify("+{$minutes} minutes"),
        ]);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
