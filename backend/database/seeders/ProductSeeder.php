<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the brand's real catalogue: 26 slipper styles and 2 shoes.
 *
 * `database/data/products.json` is transcribed from what the client supplied
 * on 30 Sep 2026 — one folder per style, holding its photographs and a sheet
 * giving price, size range, materials and gender. Names, prices, sizes,
 * materials and departments are the client's; the photographs are theirs too,
 * resized to WebP under `frontend/public/products/`.
 *
 * Prices are in cedis (stored ×100, as pesewas). Colour names are basic
 * colours read off each photograph, which the client agreed to.
 *
 * What the sheets do NOT give is deliberately left empty rather than invented:
 * there is no description, no was-price, no cost breakdown and no collection
 * (the client's word "collection" means a style — one Product — not the
 * merchandising grouping `collections` models). See FOR_THE_TEAM.md.
 *
 * These replace the six demo products the storefront was mocked up with.
 */
class ProductSeeder extends Seeder
{
    /**
     * Local and test databases only. The sheets give a size range and no
     * quantities — the brand enters real counts from the admin inventory
     * screen — but a product with no stock cannot be added to a cart, so
     * outside production every size gets the same nominal count to keep
     * checkout exercisable. Production seeds zero: a made-up number there is
     * a pair the shop would sell and not have.
     */
    private const DEV_STOCK_PER_SIZE = 5;

    public function run(): void
    {
        $path = database_path('data/products.json');

        if (! is_file($path)) {
            throw new RuntimeException("Missing product seed fixture: {$path}");
        }

        $products = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($products as $entry) {
            // The client's own split — the two folders they sent were
            // "Slippers" and "Shoe".
            $category = Category::query()->firstOrCreate(
                ['slug' => $entry['category']],
                ['name' => str($entry['category'])->headline()->toString()],
            );

            $product = Product::query()->updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'category_id' => $category->id,
                    'base_price_ghs' => $entry['base_price_ghs'],
                    // Deterministic from the slug so re-seeding doesn't churn SKUs.
                    'sku' => strtoupper(substr(md5($entry['slug']), 0, 8)),
                    'images' => $entry['images'],
                    'color' => $entry['color'],
                    'colors' => $entry['colors'],
                    'colour_images' => $entry['colour_images'] ?? [],
                    'product_type' => $entry['product_type'],
                    'departments' => $entry['departments'],
                    'materials' => $entry['materials'],
                    'is_active' => true,
                    'is_featured' => $entry['is_featured'] ?? false,
                ],
            );

            $this->seedInventory(
                $product,
                $entry['sizes'],
                array_column($entry['colors'] ?? [], 'name'),
            );
        }
    }

    /**
     * One InventoryItem per colour × size.
     *
     * The client's sheets give one size range per style, so every colourway
     * is seeded across the whole range; one a colour isn't made in simply
     * stays at 0 in admin. A style with no colours gets size-only rows.
     *
     * An existing row keeps its quantity: re-running the seeder against a
     * database where the brand has entered real stock must not reset it.
     *
     * @param  array<int, string>  $sizes
     * @param  array<int, string>  $colours
     */
    private function seedInventory(Product $product, array $sizes, array $colours): void
    {
        foreach ($colours ?: [null] as $colour) {
            foreach ($sizes as $size) {
                // Matched with explicit JSON-path where()s rather than through
                // firstOrCreate's attribute array: that array doubles as the
                // attributes for a create, and `variant_attributes->size` is
                // not a fillable column name.
                $exists = InventoryItem::query()
                    ->where('product_id', $product->id)
                    ->where('variant_attributes->size', (string) $size)
                    ->when($colour, fn ($query) => $query->where('variant_attributes->colour', $colour))
                    ->exists();

                if ($exists) {
                    continue;
                }

                InventoryItem::query()->create([
                    'product_id' => $product->id,
                    'variant_attributes' => array_filter(['size' => (string) $size, 'colour' => $colour]),
                    'quantity_available' => app()->isProduction() ? 0 : self::DEV_STOCK_PER_SIZE,
                    'quantity_reserved' => 0,
                    'low_stock_threshold' => 2,
                ]);
            }
        }
    }
}
