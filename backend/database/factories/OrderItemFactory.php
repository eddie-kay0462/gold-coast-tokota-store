<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'inventory_item_id' => null,
            // Snapshotted on the real write path too — a receipt keeps saying
            // what was bought after the product is renamed or deleted.
            'product_name' => fake()->words(3, true),
            'variant_label' => (string) fake()->numberBetween(38, 46),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->numberBetween(5_000, 20_000),
            'currency' => 'GHS',
        ];
    }
}
