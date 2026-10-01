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
}
