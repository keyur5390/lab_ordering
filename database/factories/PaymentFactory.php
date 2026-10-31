<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayName;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state: a pending Stripe checkout for an online order.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->state(['payment_method' => PaymentMethod::Online]),
            'gateway' => PaymentGatewayName::Stripe,
            'reference' => 'cs_test_'.Str::random(24),
            'status' => PaymentStatus::Pending,
            'currency' => 'INR',
            'amount' => 50000,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'gateway_payment_id' => 'pi_'.Str::random(24),
            'paid_at' => now(),
        ]);
    }
}
