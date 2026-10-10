<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnRequest>
 */
class ReturnRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reason' => fake()->randomElement(['defective', 'wrong_item', 'damaged_in_transit', 'size_exchange']),
            'status' => 'requested',
            'is_eligible' => true,
            'ineligible_reason' => null,
            'refund_amount' => null,
            'window_closes_at' => now()->addDays(5),
            'requested_at' => now(),
            'notes' => fake()->sentence(),
        ];
    }
}
