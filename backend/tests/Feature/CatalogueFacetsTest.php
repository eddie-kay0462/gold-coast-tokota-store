<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shop sidebar's filter options come from the catalogue. */
class CatalogueFacetsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $type, ?string $color, array $colors, bool $active = true): Product
    {
        return Product::factory()->create([
            'product_type' => $type, 'color' => $color, 'colors' => $colors, 'is_active' => $active,
        ]);
    }

    private function facets(): array
    {
        return $this->getJson('/api/v1/products/facets')->assertOk()->json('data');
    }

    public function test_types_are_the_ones_active_products_have_most_first(): void
    {
        $this->product('ahenema', 'Black', []);
        $this->product('slippers', 'Black', []);
        $this->product('slippers', 'Tan', []);
        $this->product('merchandise', 'Black', [], active: false);

        $this->assertSame([
            ['value' => 'slippers', 'label' => 'Slippers', 'count' => 2],
            ['value' => 'ahenema', 'label' => 'Ahenema', 'count' => 1],
        ], $this->facets()['types']);
    }

    public function test_colours_carry_the_swatch_products_are_entered_with(): void
    {
        $this->product('slippers', 'Tan', [['name' => 'Tan', 'hex' => '#b8732e'], ['name' => 'Purple', 'hex' => '#3B1F5B']]);
        $this->product('ahenema', 'Tan', [['name' => 'Tan', 'hex' => '#B8732E']]);
        // A one-off odd hex doesn't win over what most products use.
        $this->product('ahenema', 'Tan', [['name' => 'Tan', 'hex' => '#FF0000']]);

        $colors = collect($this->facets()['colors'])->keyBy('value');
        $this->assertSame(['value' => 'tan', 'label' => 'Tan', 'hex' => '#B8732E', 'count' => 3], $colors['tan']);
        $this->assertSame('#3B1F5B', $colors['purple']['hex']);
        $this->assertFalse($colors->has('orange'));
    }

    public function test_a_colour_with_no_hex_anywhere_still_gets_a_swatch(): void
    {
        $this->product('slippers', 'Ochre', []);

        $this->assertSame('#D9D9D9', collect($this->facets()['colors'])->firstWhere('value', 'ochre')['hex']);
    }

    public function test_each_count_matches_what_the_filter_returns(): void
    {
        $this->product('slippers', 'Black', [['name' => 'Black', 'hex' => '#000000'], ['name' => 'Red', 'hex' => '#C6212B']]);
        $this->product('ahenema', 'Red', [['name' => 'Red', 'hex' => '#C6212B']]);
        $this->product('slippers', 'Navy', [['name' => 'Navy', 'hex' => '#1B2A4A']]);

        $facets = $this->facets();
        foreach ($facets['colors'] as $color) {
            $this->assertSame($color['count'], $this->getJson('/api/v1/products?color='.$color['value'])->json('meta.total'), $color['value']);
        }
        foreach ($facets['types'] as $type) {
            $this->assertSame($type['count'], $this->getJson('/api/v1/products?type='.$type['value'])->json('meta.total'), $type['value']);
        }
    }

    public function test_a_product_change_shows_up_straight_away(): void
    {
        $this->product('slippers', 'Black', []);
        $this->facets();

        $this->product('closed-toe', 'Green', [['name' => 'Green', 'hex' => '#2F4A3A']]);

        $this->assertContains('closed-toe', array_column($this->facets()['types'], 'value'));
        $this->assertContains('green', array_column($this->facets()['colors'], 'value'));
    }
}
