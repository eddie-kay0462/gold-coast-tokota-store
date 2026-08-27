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

    /**
     * The storefront's ApiProduct type (frontend/utils/catalog.ts) is the
     * contract. Everything it declares must survive a round trip through the
     * API, or the pages built against it silently fall back to fixtures.
     */
    public function test_product_resource_exposes_every_storefront_contract_field(): void
    {
        $product = Product::factory()->onSale()->create([
            'product_type' => 'ahenema',
            'departments' => ['mens', 'womens'],
            'widths' => ['m', 'l'],
            'tags' => ['Custom Made'],
            'color' => 'Brown',
            'colors' => [['name' => 'Brown', 'hex' => '#8B5A2B']],
            'is_pre_order' => true,
            'description_heading' => 'Woven Heritage, Everyday Wear',
            'model_note' => 'Model is 5′11″, wearing a size 42',
            'cost_breakdown' => [
                ['label' => 'Materials', 'amount_ghs' => 4500, 'icon' => '/design/icons/cost-materials.svg'],
            ],
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.product_type', 'ahenema');
        $response->assertJsonPath('data.departments', ['mens', 'womens']);
        $response->assertJsonPath('data.widths', ['m', 'l']);
        $response->assertJsonPath('data.tags', ['Custom Made']);
        $response->assertJsonPath('data.color', 'Brown');
        $response->assertJsonPath('data.colors.0.hex', '#8B5A2B');
        $response->assertJsonPath('data.is_pre_order', true);
        $response->assertJsonPath('data.description_heading', 'Woven Heritage, Everyday Wear');
        $response->assertJsonPath('data.model_note', 'Model is 5′11″, wearing a size 42');
        $response->assertJsonPath('data.cost_breakdown.0.label', 'Materials');
        $response->assertJsonPath('data.compare_at_ghs', $product->compare_at_ghs);
    }

    /** Listing responses carry the facets too — the shop page filters on them client-side. */
    public function test_listing_responses_carry_the_listing_facets(): void
    {
        Product::factory()->create(['product_type' => 'slippers', 'departments' => ['womens']]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonPath('data.0.product_type', 'slippers');
        $response->assertJsonPath('data.0.departments', ['womens']);
    }

    /**
     * PHP casts numeric array keys to ints, but the storefront's size facet
     * compares these against strings — an int list matches nothing, silently.
     */
    public function test_sizes_are_returned_as_strings(): void
    {
        $product = Product::factory()->create();
        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 5,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $this->assertSame(['42'], $response->json('data.sizes'));
    }

    /** A sale price and its was-price must never be converted on different rates. */
    public function test_compare_at_usd_is_derived_on_the_same_rate_as_price_usd(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $product = Product::factory()->create([
            'base_price_ghs' => 60000,
            'compare_at_ghs' => 70000,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.price_usd', 4800);
        $response->assertJsonPath('data.compare_at_usd', 5600);
    }

    public function test_compare_at_usd_is_null_when_the_product_is_not_on_sale(): void
    {
        FxRate::factory()->create(['rate' => 0.08]);
        $product = Product::factory()->create(['compare_at_ghs' => null]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.compare_at_usd', null);
    }

    /** A product with no photo renders the detail gallery as a bare grey frame. */
    public function test_factory_products_always_carry_at_least_one_image(): void
    {
        $product = Product::factory()->create();

        $this->assertNotEmpty($product->images);
    }
}
