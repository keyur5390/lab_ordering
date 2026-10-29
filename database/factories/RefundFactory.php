<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->paid(),
            'status' => RefundStatus::Succeeded,
            'amount' => 10000,
            'gateway_refund_id' => 're_'.Str::random(24),
        ];
    }
}
