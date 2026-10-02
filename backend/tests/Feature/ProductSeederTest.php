<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_clients_catalogue(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertSame(28, Product::query()->count());
        $this->assertSame(26, Category::query()->where('slug', 'slippers')->firstOrFail()->products()->count());
        $this->assertSame(2, Category::query()->where('slug', 'shoes')->firstOrFail()->products()->count());

        // Prices are the client's cedi figures in pesewas.
        $this->assertSame(50000, Product::query()->where('slug', 'abrantie')->value('base_price_ghs'));
        $this->assertSame(30000, Product::query()->where('slug', 'obaapa')->value('base_price_ghs'));
        $this->assertSame(70000, Product::query()->where('slug', 'osram')->value('base_price_ghs'));
    }

    public function test_sizes_departments_and_materials_follow_the_clients_sheets(): void
    {
        $this->seed(ProductSeeder::class);

        $ohene = Product::query()->with('inventoryItems')->where('slug', 'ohene')->firstOrFail();

        $this->assertSame(['36', '37', '38', '39', '40', '41', '42', '43', '44', '45'], $ohene->sizes);
        $this->assertSame(['mens', 'womens'], $ohene->departments);
        $this->assertContains('Adinkra symbol', $ohene->materials);
        $this->assertTrue($ohene->in_stock);

        $this->getJson('/api/v1/products/ohene')
            ->assertOk()
            ->assertJsonPath('data.materials', $ohene->materials);
    }

    /**
     * Image paths are plain strings pointing into the storefront's public
     * directory, so nothing stops the fixture and the files drifting apart.
     */
    public function test_every_seeded_image_exists_in_the_storefront(): void
    {
        $this->seed(ProductSeeder::class);

        foreach (Product::query()->get() as $product) {
            $this->assertNotEmpty($product->images, "{$product->slug} has no images");

            foreach ($product->images as $image) {
                $this->assertFileExists(base_path('../frontend/public'.$image));
            }
        }
    }

    public function test_production_seeds_every_size_with_no_stock(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Run directly: `db:seed` asks for confirmation in production.
        $this->app->make(ProductSeeder::class)->run();

        $this->assertSame(0, (int) InventoryItem::query()->sum('quantity_available'));
        $this->assertSame(
            ['40', '41', '42', '43', '44', '45'],
            Product::query()->with('inventoryItems')->where('slug', 'abrantie')->firstOrFail()->sizes,
        );
    }

    public function test_reseeding_does_not_reset_stock_that_has_since_been_entered(): void
    {
        $this->seed(ProductSeeder::class);

        $item = InventoryItem::query()->firstOrFail();
        $item->update(['quantity_available' => 17]);

        $this->seed(ProductSeeder::class);

        $this->assertSame(17, $item->fresh()->quantity_available);
        $this->assertSame(28, Product::query()->count());
    }

    // --- colour variants (issue 39) ---------------------------------------

    public function test_every_colourway_is_stocked_across_the_whole_size_range(): void
    {
        $this->seed(ProductSeeder::class);

        $domfo = Product::query()->with('inventoryItems')->where('slug', 'domfo')->firstOrFail();

        // Four colourways × sizes 40–45.
        $this->assertCount(24, $domfo->inventoryItems);
        $this->assertSame(['Tan', 'Green', 'Blue', 'Black'], array_keys($domfo->variant_availability));
        // PHP turns numeric-string keys into ints; JSON keys are strings again.
        $this->assertSame(['40', '41', '42', '43', '44', '45'], array_map(strval(...), array_keys($domfo->variant_availability['Green'])));
    }

    public function test_reseeding_adds_no_duplicate_colour_rows(): void
    {
        $this->seed(ProductSeeder::class);
        $before = InventoryItem::query()->count();

        $this->seed(ProductSeeder::class);

        $this->assertSame($before, InventoryItem::query()->count());
    }

    public function test_each_colourway_has_its_own_photographs(): void
    {
        $this->seed(ProductSeeder::class);

        foreach (Product::query()->get() as $product) {
            $colours = array_column($product->colors, 'name');
            $this->assertSame($colours, array_keys($product->colour_images), "{$product->slug} colour_images do not match its colours");

            foreach ($product->colour_images as $colour => $images) {
                $this->assertNotEmpty($images, "{$product->slug} has no {$colour} photo");
                foreach ($images as $image) {
                    $this->assertContains($image, $product->images);
                }
            }
        }
    }

    public function test_the_product_api_reports_stock_per_colour_and_size(): void
    {
        $this->seed(ProductSeeder::class);

        $green41 = InventoryItem::query()
            ->whereHas('product', fn ($query) => $query->where('slug', 'domfo'))
            ->where('variant_attributes->colour', 'Green')
            ->where('variant_attributes->size', '41')
            ->firstOrFail();
        $green41->update(['quantity_available' => 0]);

        $this->getJson('/api/v1/products/domfo')
            ->assertOk()
            ->assertJsonPath('data.variant_availability.Green.41', 0)
            ->assertJsonPath('data.variant_availability.Tan.41', 5)
            // The all-colours sum still counts the other three.
            ->assertJsonPath('data.size_availability.41', 15)
            ->assertJsonPath('data.colour_images.Green', ['/products/domfo/2-green.webp']);

        $this->getJson('/api/v1/products/domfo/stock')
            ->assertOk()
            ->assertJsonPath('data.variant_availability.Green.41', 0);
    }
}
