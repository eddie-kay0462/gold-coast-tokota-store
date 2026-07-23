<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_index_lists_only_active_products(): void
    {
        Product::factory()->count(2)->create();
        Product::factory()->inactive()->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_product_index_filters_by_category(): void
    {
        $sandals = Category::factory()->create();
        $accessories = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $sandals->id]);
        Product::factory()->create(['category_id' => $accessories->id]);

        $response = $this->getJson("/api/v1/products?category_id={$sandals->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_product_index_filters_by_featured(): void
    {
        Product::factory()->count(2)->featured()->create();
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products?featured=true');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_product_index_paginates(): void
    {
        Product::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $this->assertCount(12, $response->json('data'));
        $response->assertJsonPath('meta.total', 15);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_product_show_returns_active_product_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'artisan-sandal']);

        $response = $this->getJson('/api/v1/products/artisan-sandal');

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
    }

    public function test_product_show_returns_404_for_inactive_product(): void
    {
        Product::factory()->inactive()->create(['slug' => 'hidden-product']);

        $this->getJson('/api/v1/products/hidden-product')->assertNotFound();
    }

    public function test_product_show_returns_404_for_missing_slug(): void
    {
        $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
    }

    public function test_price_usd_is_derived_from_the_latest_cached_fx_rate(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $product = Product::factory()->create(['base_price_ghs' => 10_000]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.price_usd', 800);
    }

    public function test_price_usd_is_null_when_no_fx_rate_has_ever_been_fetched(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.price_usd', null);
    }

    public function test_category_index_lists_all_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_collection_index_lists_all_collections(): void
    {
        Collection::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/collections');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_merchandising_badge_is_out_of_stock_when_no_stock_available(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->outOfStock()->create(['product_id' => $product->id, 'low_stock_threshold' => 5]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.merchandising_badge', 'out_of_stock');
    }

    public function test_merchandising_badge_is_limited_stock_when_available_at_or_below_threshold(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 3,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.merchandising_badge', 'limited_stock');
    }

    public function test_merchandising_badge_is_null_when_stock_is_healthy_and_no_admin_badge_set(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 50,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.merchandising_badge', null);
    }

    public function test_merchandising_badge_reflects_admin_set_back_in_stock_when_stock_is_healthy(): void
    {
        $product = Product::factory()->create(['merchandising_badge' => 'back_in_stock']);
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 50,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.merchandising_badge', 'back_in_stock');
    }

    public function test_fully_reserved_stock_reads_as_out_of_stock_even_though_physically_available(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 3,
            'quantity_reserved' => 3,
            'low_stock_threshold' => 0,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.in_stock', false);
        $response->assertJsonPath('data.merchandising_badge', 'out_of_stock');
    }

    public function test_partially_reserved_stock_still_shows_as_in_stock_for_the_remainder(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'quantity_available' => 10,
            'quantity_reserved' => 3,
            'low_stock_threshold' => 0,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.in_stock', true);
        $response->assertJsonPath('data.merchandising_badge', null);
    }
}
