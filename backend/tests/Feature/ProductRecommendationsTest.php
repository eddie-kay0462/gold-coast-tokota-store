<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug, array $departments, int $stock = 5, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'departments' => $departments,
            'is_featured' => false,
        ], $attributes));

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '40'],
            'quantity_available' => $stock,
            'quantity_reserved' => 0,
        ]);

        return $product;
    }

    private function slugs(string $query): array
    {
        return array_column($this->getJson("/api/v1/products/recommendations?{$query}")->assertOk()->json('data'), 'slug');
    }

    public function test_the_product_being_viewed_is_never_recommended_back(): void
    {
        $this->product('domfo', ['mens']);
        $this->product('osram', ['mens']);

        $this->assertNotContains('domfo', $this->slugs('for=domfo'));
    }

    public function test_styles_from_the_same_department_come_first(): void
    {
        $this->product('obaapa', ['womens']);
        $this->product('aaa-mens', ['mens']);
        $this->product('zzz-womens', ['womens']);

        // Alphabetically the men's style would lead; the department wins.
        $this->assertSame(['zzz-womens', 'aaa-mens'], $this->slugs('for=obaapa'));
    }

    public function test_in_stock_styles_rank_above_sold_out_ones_but_sold_out_still_fill(): void
    {
        $this->product('domfo', ['mens']);
        $this->product('aaa-sold-out', ['mens'], stock: 0);
        $this->product('zzz-in-stock', ['mens']);

        $this->assertSame(['zzz-in-stock', 'aaa-sold-out'], $this->slugs('for=domfo'));
    }

    public function test_everything_already_in_the_cart_is_excluded(): void
    {
        $this->product('domfo', ['mens']);
        $this->product('osram', ['mens']);
        $this->product('odeneho', ['mens']);

        $this->assertSame(['odeneho'], $this->slugs('for=domfo,osram'));
    }

    public function test_inactive_styles_are_never_recommended(): void
    {
        $this->product('domfo', ['mens']);
        $this->product('retired', ['mens'], attributes: ['is_active' => false]);

        $this->assertSame([], $this->slugs('for=domfo'));
    }

    public function test_the_limit_is_honoured_and_capped(): void
    {
        foreach (range(1, 14) as $n) {
            $this->product("style-{$n}", ['mens']);
        }

        $this->assertCount(4, $this->slugs(''));
        $this->assertCount(6, $this->slugs('limit=6'));
        $this->assertCount(12, $this->slugs('limit=100'));
    }

    public function test_the_route_is_not_swallowed_by_the_product_slug_route(): void
    {
        $this->product('domfo', ['mens']);

        $this->getJson('/api/v1/products/recommendations')->assertOk()->assertJsonStructure(['data']);
    }
}
