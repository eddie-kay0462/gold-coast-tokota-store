<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_attributes' => ['size' => fake()->randomElement(['38', '39', '40', '41', '42', '43'])],
            'quantity_available' => fake()->numberBetween(0, 50),
            'quantity_reserved' => 0,
            'reservation_expires_at' => null,
            'low_stock_threshold' => 5,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['quantity_available' => 0]);
    }
}
