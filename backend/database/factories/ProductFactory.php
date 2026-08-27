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
    /** Colourway names + swatch hexes drawn from the design catalogue. */
    private const COLORS = [
        ['name' => 'Brown', 'hex' => '#8B5A2B'],
        ['name' => 'Navy Blue', 'hex' => '#1B2A4A'],
        ['name' => 'Tan', 'hex' => '#C4AE93'],
        ['name' => 'Black', 'hex' => '#000000'],
        ['name' => 'Olive Green', 'hex' => '#5A6134'],
        ['name' => 'Crystal Blue', 'hex' => '#7FA8C9'],
    ];

    /** Shipped product photography, so a factory-made product is never imageless. */
    private const IMAGES = [
        '/design/product-kentehene.png',
        '/design/product-acheampong.png',
        '/design/product-adinkra.png',
        '/design/product-odeneho.png',
        '/design/product-elevated-odeneho.png',
        '/design/product-flavourful-cross.png',
    ];

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $basePrice = fake()->numberBetween(5_000, 80_000);
        $colors = fake()->randomElements(self::COLORS, fake()->numberBetween(1, 4));

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'description_heading' => Str::title(fake()->words(4, true)),
            'model_note' => 'Model is 5′11″, wearing a size 42',
            'category_id' => Category::factory(),
            // Minor units (pesewas) — e.g. 15000 = GH₵150.00.
            'base_price_ghs' => $basePrice,
            'sku' => strtoupper(Str::random(8)),
            // A real path, not []. An empty array renders the product detail
            // page as a bare grey frame — ProductGallery has no placeholder,
            // unlike the product card, so the omission was invisible on the
            // listing and total on the detail page.
            'images' => [fake()->randomElement(self::IMAGES)],
            'color' => $colors[0]['name'],
            'colors' => array_values($colors),
            'product_type' => fake()->randomElement(['ahenema', 'slippers', 'sandals', 'closed-toe']),
            'departments' => fake()->randomElements(['mens', 'womens', 'kids'], fake()->numberBetween(1, 2)),
            'widths' => fake()->randomElements(['s', 'm', 'l'], fake()->numberBetween(1, 2)),
            'tags' => [],
            'is_active' => true,
            'is_featured' => false,
            'is_pre_order' => false,
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

    public function preOrder(): static
    {
        return $this->state(fn () => ['is_pre_order' => true]);
    }

    /** On sale — a was-price strictly above the selling price, as the storefront requires. */
    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'compare_at_ghs' => (int) round($attributes['base_price_ghs'] * 1.25),
        ]);
    }
}
