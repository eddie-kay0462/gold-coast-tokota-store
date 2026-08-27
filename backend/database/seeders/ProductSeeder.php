<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the six products the storefront was designed around.
 *
 * The copy, pricing, photography and per-size stock in
 * `database/data/design-products.json` were generated from the frontend's
 * DESIGN_PRODUCTS fixture, which is what the shop listing and product detail
 * pages fall back to when the API has nothing useful to say. Seeding the same
 * six from the API is what lets that fixture be deleted: the pages render
 * identically whether the data comes from Postgres or from the fallback.
 *
 * Faker products remain (DatabaseSeeder still makes a few) so that pagination,
 * filtering and the empty states have more than six rows to work against — but
 * they are no longer the only thing a developer sees.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/design-products.json');

        if (! is_file($path)) {
            throw new RuntimeException("Missing product seed fixture: {$path}");
        }

        $products = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($products as $entry) {
            $category = Category::query()->firstOrCreate(
                ['slug' => $entry['category']],
                ['name' => str($entry['category'])->headline()->toString()],
            );

            $collection = Collection::query()->firstOrCreate(
                ['slug' => $entry['collection']],
                ['name' => str($entry['collection'])->headline()->toString()],
            );

            $product = Product::query()->updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'description' => $entry['description'] ?? null,
                    'description_heading' => $entry['description_heading'] ?? null,
                    'model_note' => $entry['model_note'] ?? null,
                    'category_id' => $category->id,
                    'collection_id' => $collection->id,
                    'base_price_ghs' => $entry['base_price_ghs'],
                    'compare_at_ghs' => $entry['compare_at_ghs'] ?? null,
                    // Deterministic from the slug so re-seeding doesn't churn SKUs.
                    'sku' => strtoupper(substr(md5($entry['slug']), 0, 8)),
                    'images' => $entry['images'] ?? [],
                    'color' => $entry['color'] ?? null,
                    'colors' => $entry['colors'] ?? [],
                    'product_type' => $entry['product_type'] ?? null,
                    'departments' => $entry['departments'] ?? [],
                    'widths' => $entry['widths'] ?? [],
                    'tags' => $entry['tags'] ?? [],
                    'cost_breakdown' => $entry['cost_breakdown'] ?? [],
                    'is_active' => true,
                    'is_featured' => $entry['is_featured'] ?? false,
                    'is_pre_order' => $entry['is_pre_order'] ?? false,
                ],
            );

            $this->seedInventory($product, $entry['size_availability'] ?? []);
        }
    }

    /**
     * One InventoryItem per size, carrying the design's own stock numbers —
     * including the deliberate zeroes, which are what make the struck-through
     * sizes and the OUT OF STOCK badge appear on a seeded database.
     *
     * @param  array<string, int>  $availability
     */
    private function seedInventory(Product $product, array $availability): void
    {
        foreach ($availability as $size => $quantity) {
            // Matched with an explicit JSON-path where() rather than through
            // updateOrCreate's attribute array: that array doubles as the
            // attributes for a create, and `variant_attributes->size` is not a
            // fillable column name.
            $item = InventoryItem::query()
                ->where('product_id', $product->id)
                ->where('variant_attributes->size', (string) $size)
                ->first() ?? new InventoryItem(['product_id' => $product->id]);

            $item->fill([
                'variant_attributes' => ['size' => (string) $size],
                'quantity_available' => $quantity,
                'quantity_reserved' => 0,
                // Low enough that a healthy size isn't permanently flagged,
                // high enough that the design's 1- and 2-unit sizes trip the
                // LIMITED STOCK badge the mockup shows.
                'low_stock_threshold' => 2,
            ])->save();
        }
    }
}
