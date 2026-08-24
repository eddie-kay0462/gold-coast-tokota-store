<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'category_id' => Category::factory(),
            // Minor units (pesewas) — e.g. 15000 = GH₵150.00.
            'base_price_ghs' => fake()->numberBetween(5_000, 80_000),
            'sku' => strtoupper(Str::random(8)),
            'images' => [],
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
