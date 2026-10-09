<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-side filtering for `GET /products` — the query string
 * `frontend/pages/shop/index.vue` already sends.
 *
 * The point of most of these is the one the old client-side filtering could
 * not satisfy: a facet has to hold across the *whole* catalogue, not just the
 * first page. Several tests deliberately create more than one page of products
 * for that reason.
 */
class CatalogueFilterTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'departments' => [],
            'widths' => [],
            'tags' => [],
            'colors' => [],
            ...$attributes,
        ]);
    }

    /** @return list<string> */
    private function slugs($response): array
    {
        return array_column($response->json('data'), 'slug');
    }

    // ------------------------------------------------------------- the point

    public function test_a_facet_matches_across_the_whole_catalogue_not_just_the_first_page(): void
    {
        // 20 products the facet must not match, so the one that does falls well
        // outside the first page of 12. This is the exact bug the old
        // client-side filtering had.
        $this->product(['product_type' => 'sandals', 'slug' => 'needle-in-haystack']);
        Product::factory()->count(20)->create(['product_type' => 'slippers', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?type=sandals');

        $response->assertOk();
        $this->assertSame(['needle-in-haystack'], $this->slugs($response));
        $response->assertJsonPath('meta.total', 1);
    }

    // ---------------------------------------------------------------- facets

    public function test_filters_by_product_type(): void
    {
        $this->product(['product_type' => 'ahenema', 'slug' => 'a']);
        $this->product(['product_type' => 'slippers', 'slug' => 'b']);

        $response = $this->getJson('/api/v1/products?type=ahenema');

        $this->assertSame(['a'], $this->slugs($response));
    }

    public function test_several_values_in_one_facet_widen_the_result(): void
    {
        $this->product(['product_type' => 'ahenema', 'slug' => 'a']);
        $this->product(['product_type' => 'slippers', 'slug' => 'b']);
        $this->product(['product_type' => 'sandals', 'slug' => 'c']);

        // Within a facet the checkboxes are OR — ticking two colours shows
        // more, not fewer.
        $response = $this->getJson('/api/v1/products?type=ahenema,slippers');

        $this->assertEqualsCanonicalizing(['a', 'b'], $this->slugs($response));
    }

    public function test_different_facets_narrow_the_result(): void
    {
        $this->product(['product_type' => 'ahenema', 'slug' => 'match', 'widths' => ['m']]);
        $this->product(['product_type' => 'ahenema', 'slug' => 'wrong-width', 'widths' => ['l']]);
        $this->product(['product_type' => 'slippers', 'slug' => 'wrong-type', 'widths' => ['m']]);

        // Across facets it is AND.
        $response = $this->getJson('/api/v1/products?type=ahenema&width=m');

        $this->assertSame(['match'], $this->slugs($response));
    }

    public function test_filters_by_colour_as_a_substring_of_the_colourway(): void
    {
        // The storefront matches a facet value against colourway *names*, so
        // `tan` has to find "Tan Leather". Containment cannot express that.
        $this->product(['slug' => 'tan-one', 'color' => 'Tan Leather', 'colors' => [['name' => 'Tan Leather']]]);
        $this->product(['slug' => 'black-one', 'color' => 'Jet Black', 'colors' => [['name' => 'Jet Black']]]);

        $response = $this->getJson('/api/v1/products?color=tan');

        $this->assertSame(['tan-one'], $this->slugs($response));
    }

    public function test_colour_matches_a_secondary_colourway_not_just_the_primary(): void
    {
        $this->product([
            'slug' => 'two-tone',
            'color' => 'Jet Black',
            'colors' => [['name' => 'Jet Black'], ['name' => 'Olive Green']],
        ]);

        $response = $this->getJson('/api/v1/products?color=olive');

        $this->assertSame(['two-tone'], $this->slugs($response));
    }

    public function test_filters_by_width(): void
    {
        $this->product(['slug' => 'wide', 'widths' => ['l']]);
        $this->product(['slug' => 'narrow', 'widths' => ['s']]);

        $this->assertSame(['wide'], $this->slugs($this->getJson('/api/v1/products?width=l')));
    }

    public function test_filters_by_department(): void
    {
        // `?category=` is the department, not the catalogue category — the one
        // piece of this contract that reads wrong unless you know it.
        $this->product(['slug' => 'for-men', 'departments' => ['mens']]);
        $this->product(['slug' => 'for-kids', 'departments' => ['kids']]);

        $this->assertSame(['for-men'], $this->slugs($this->getJson('/api/v1/products?category=mens')));
    }

    public function test_filters_by_size_across_variants(): void
    {
        $sized = $this->product(['slug' => 'has-42']);
        InventoryItem::factory()->for($sized)->create(['variant_attributes' => ['size' => '42']]);

        $other = $this->product(['slug' => 'has-39']);
        InventoryItem::factory()->for($other)->create(['variant_attributes' => ['size' => '39']]);

        $this->assertSame(['has-42'], $this->slugs($this->getJson('/api/v1/products?size=42')));
    }

    public function test_a_size_that_is_made_but_sold_out_still_matches(): void
    {
        // The storefront renders sold-out sizes struck through rather than
        // hiding them, so filtering them out here would make the two disagree.
        $product = $this->product(['slug' => 'sold-out-42']);
        InventoryItem::factory()->for($product)->create([
            'variant_attributes' => ['size' => '42'],
            'quantity_available' => 0,
            'quantity_reserved' => 0,
        ]);

        $this->assertSame(['sold-out-42'], $this->slugs($this->getJson('/api/v1/products?size=42')));
    }

    public function test_filters_by_sale(): void
    {
        $this->product(['slug' => 'on-sale', 'base_price_ghs' => 10_000, 'compare_at_ghs' => 15_000]);
        $this->product(['slug' => 'full-price', 'base_price_ghs' => 10_000, 'compare_at_ghs' => null]);
        // Equal is not a discount, and presenting it as one would be a claim
        // nobody authorised.
        $this->product(['slug' => 'not-really', 'base_price_ghs' => 10_000, 'compare_at_ghs' => 10_000]);

        $this->assertSame(['on-sale'], $this->slugs($this->getJson('/api/v1/products?sale=true')));
    }

    // ---------------------------------------------------------------- search

    public function test_search_covers_name_colour_type_and_tags(): void
    {
        $this->product(['slug' => 'by-name', 'name' => 'Kentehene Slide', 'tags' => []]);
        $this->product(['slug' => 'by-tag', 'name' => 'Something Else', 'tags' => ['kentehene']]);
        $this->product(['slug' => 'unrelated', 'name' => 'Odeneho', 'tags' => ['plain']]);

        $response = $this->getJson('/api/v1/products?q=kentehene');

        $this->assertEqualsCanonicalizing(['by-name', 'by-tag'], $this->slugs($response));
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->product(['slug' => 'mixed-case', 'name' => 'Kentehene Slide']);

        $this->assertSame(['mixed-case'], $this->slugs($this->getJson('/api/v1/products?q=KENTEHENE')));
        $this->assertSame(['mixed-case'], $this->slugs($this->getJson('/api/v1/products?q=kEnTeHeNe')));
    }

    public function test_search_combines_with_a_facet(): void
    {
        $this->product(['slug' => 'both', 'name' => 'Kente Sandal', 'product_type' => 'sandals']);
        $this->product(['slug' => 'name-only', 'name' => 'Kente Slipper', 'product_type' => 'slippers']);

        $response = $this->getJson('/api/v1/products?q=kente&type=sandals');

        $this->assertSame(['both'], $this->slugs($response));
    }

    // ------------------------------------------------------------------ sort

    public function test_sort_newest_puts_the_most_recent_first(): void
    {
        $this->product(['slug' => 'older', 'created_at' => now()->subWeek()]);
        $this->product(['slug' => 'newer', 'created_at' => now()]);

        $this->assertSame(['newer', 'older'], $this->slugs($this->getJson('/api/v1/products?sort=newest')));
    }

    public function test_the_default_sort_puts_featured_products_first(): void
    {
        // The storefront labels the unsorted view "Featured", so that is what
        // the word has to mean here.
        $this->product(['slug' => 'ordinary', 'is_featured' => false, 'created_at' => now()]);
        $this->product(['slug' => 'featured', 'is_featured' => true, 'created_at' => now()->subWeek()]);

        $this->assertSame(['featured', 'ordinary'], $this->slugs($this->getJson('/api/v1/products')));
    }

    public function test_sort_best_selling_counts_units_on_paid_orders(): void
    {
        $quiet = $this->product(['slug' => 'quiet']);
        $popular = $this->product(['slug' => 'popular']);

        $this->sell($quiet, 1, 'paid');
        $this->sell($popular, 9, 'delivered');

        $this->assertSame(['popular', 'quiet'], $this->slugs($this->getJson('/api/v1/products?sort=best-selling')));
    }

    public function test_cancelled_and_refunded_orders_do_not_count_as_sales(): void
    {
        $genuine = $this->product(['slug' => 'genuine']);
        $returned = $this->product(['slug' => 'returned']);

        $this->sell($genuine, 2, 'paid');
        // A refunded sale is not a sale. Counting it would let a product that
        // everybody sent back lead the best-sellers.
        $this->sell($returned, 50, 'refunded');
        $this->sell($returned, 50, 'cancelled');

        $this->assertSame(['genuine', 'returned'], $this->slugs($this->getJson('/api/v1/products?sort=best-selling')));
    }

    /**
     * There is no ratings data anywhere in this system — product reviews are
     * unplanned scope with a built UI and no model (open decision 24). The
     * sort therefore cannot be honoured, and a shopper who clicks "Top Rated"
     * should still get products rather than an error.
     */
    public function test_an_unsupported_sort_falls_back_to_the_default_rather_than_failing(): void
    {
        $this->product(['slug' => 'ordinary', 'is_featured' => false]);
        $this->product(['slug' => 'featured', 'is_featured' => true]);

        $response = $this->getJson('/api/v1/products?sort=top-rated');

        $response->assertOk();
        $this->assertSame(['featured', 'ordinary'], $this->slugs($response));
    }

    // ------------------------------------------------------------- behaviour

    public function test_filtering_never_exposes_an_inactive_product(): void
    {
        $this->product(['slug' => 'draft', 'product_type' => 'sandals', 'is_active' => false]);

        $this->assertSame([], $this->slugs($this->getJson('/api/v1/products?type=sandals')));
    }

    public function test_an_empty_or_trailing_comma_facet_does_not_filter_everything_out(): void
    {
        $this->product(['slug' => 'a', 'product_type' => 'sandals']);

        // A trailing comma is what an unticked last checkbox can leave behind.
        $this->assertSame(['a'], $this->slugs($this->getJson('/api/v1/products?type=sandals,')));
        // An empty facet means "no filter", not "match nothing".
        $this->assertSame(['a'], $this->slugs($this->getJson('/api/v1/products?type=')));
    }

    public function test_pagination_metadata_reflects_the_filtered_set(): void
    {
        Product::factory()->count(15)->create(['product_type' => 'sandals', 'is_active' => true]);
        Product::factory()->count(5)->create(['product_type' => 'slippers', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?type=sandals');

        // The count has to describe the filtered catalogue, or the grid's
        // paginator offers pages that do not exist.
        $response->assertJsonPath('meta.total', 15);
        $response->assertJsonPath('meta.last_page', 2);
        $this->assertCount(12, $response->json('data'));
    }

    public function test_filters_survive_onto_page_two(): void
    {
        Product::factory()->count(15)->create(['product_type' => 'sandals', 'is_active' => true]);
        Product::factory()->count(5)->create(['product_type' => 'slippers', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?type=sandals&page=2');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        foreach ($response->json('data') as $product) {
            $this->assertSame('sandals', $product['product_type']);
        }
    }

    public function test_per_page_is_capped(): void
    {
        Product::factory()->count(60)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?per_page=500');

        // A crawler must not be able to ask for the whole catalogue at once.
        $this->assertCount(48, $response->json('data'));
    }

    public function test_category_id_and_collection_id_still_work(): void
    {
        $category = Category::factory()->create();
        $collection = Collection::factory()->create();
        $this->product(['slug' => 'in-both', 'category_id' => $category->id, 'collection_id' => $collection->id]);
        $this->product(['slug' => 'in-neither']);

        $this->assertSame(['in-both'], $this->slugs(
            $this->getJson("/api/v1/products?category_id={$category->id}"),
        ));
        $this->assertSame(['in-both'], $this->slugs(
            $this->getJson("/api/v1/products?collection_id={$collection->id}"),
        ));
    }

    private function sell(Product $product, int $quantity, string $status): void
    {
        $order = Order::factory()->create(['status' => $status]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
