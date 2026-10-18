<?php

namespace App\Support\Catalogue;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;

/**
 * Writes one `database/data/products.json` entry to the database.
 *
 * Shared by ProductSeeder (every entry, every run) and the
 * `catalogue:import-media` command (creating styles the database doesn't have
 * yet), so a product made either way gets the same category, SKU and
 * inventory rows.
 */
final class ProductUpserter
{
    /**
     * Local and test databases only. The sheets give a size range and no
     * quantities — the brand enters real counts from the admin inventory
     * screen — but a product with no stock cannot be added to a cart, so
     * outside production every size gets the same nominal count to keep
     * checkout exercisable. Production seeds zero: a made-up number there is
     * a pair the shop would sell and not have.
     */
    public const DEV_STOCK_PER_SIZE = 5;

    /**
     * Create the product, or bring an existing one back in line with the
     * fixture.
     *
     * Photos are the exception. Once a product's images are media-library
     * keys (put there by `catalogue:import-media` or the admin), the
     * fixture's storefront paths would be a step backwards, so an existing
     * row keeps them.
     *
     * @param  array<string, mixed>  $entry
     */
    public function upsert(array $entry): Product
    {
        // The client's own split — the folders they sent were "Slippers",
        // "Shoe" and "Ahenema".
        $category = Category::query()->firstOrCreate(
            ['slug' => $entry['category']],
            ['name' => str($entry['category'])->headline()->toString()],
        );

        $attributes = [
            'name' => $entry['name'],
            'category_id' => $category->id,
            'base_price_ghs' => $entry['base_price_ghs'],
            // Deterministic from the slug so re-seeding doesn't churn SKUs.
            'sku' => strtoupper(substr(md5($entry['slug']), 0, 8)),
            'images' => $entry['images'] ?? [],
            'color' => $entry['color'] ?? null,
            'colors' => $entry['colors'] ?? [],
            'colour_images' => $entry['colour_images'] ?? [],
            'product_type' => $entry['product_type'],
            'departments' => $entry['departments'] ?? [],
            'materials' => $entry['materials'] ?? [],
            'is_active' => true,
            'is_featured' => $entry['is_featured'] ?? false,
        ];

        $existing = Product::query()->where('slug', $entry['slug'])->first();

        if ($existing && self::hasManagedImages($existing)) {
            unset($attributes['images'], $attributes['colour_images']);
        }

        $product = Product::query()->updateOrCreate(['slug' => $entry['slug']], $attributes);

        $this->seedInventory(
            $product,
            $entry['sizes'] ?? [],
            array_column($entry['colors'] ?? [], 'name'),
        );

        return $product;
    }

    /**
     * Brings an existing product's place in the catalogue in line with the
     * fixture, for `catalogue:import-media`: its category and product type,
     * and any colour the fixture has that the product doesn't (a new photo
     * may be of it). Price, name, materials, sizes and stock are the admin's
     * and stay as they are; a colour is only ever added, never removed.
     *
     * Returns what changes (or would change, with `$write` false), one line
     * each.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    public function catchUp(Product $product, array $entry, bool $write = true): array
    {
        $changes = [];
        $currentCategory = $product->category?->slug;

        if ($currentCategory !== $entry['category']) {
            $changes[] = "category {$currentCategory} → {$entry['category']}";
        }

        if ($product->product_type !== $entry['product_type']) {
            $changes[] = "type {$product->product_type} → {$entry['product_type']}";
        }

        $have = array_column($product->colors ?? [], 'name');
        $added = array_values(array_filter(
            $entry['colors'] ?? [],
            fn (array $colour) => ! in_array($colour['name'], $have, true),
        ));

        foreach ($added as $colour) {
            $changes[] = "adds colour {$colour['name']}";
        }

        if (! $write || $changes === []) {
            return $changes;
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => $entry['category']],
            ['name' => str($entry['category'])->headline()->toString()],
        );

        $product->forceFill([
            'category_id' => $category->id,
            'product_type' => $entry['product_type'],
            'colors' => [...($product->colors ?? []), ...$added],
        ])->save();

        // Rows for the new colours only, across the sizes the product
        // already sells.
        $sizes = InventoryItem::query()
            ->where('product_id', $product->id)
            ->pluck('variant_attributes')
            ->map(fn ($attributes) => $attributes['size'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->seedInventory($product, $sizes ?: ($entry['sizes'] ?? []), array_column($added, 'name'));

        return $changes;
    }

    /**
     * True once a product's photos live on the media disk rather than in the
     * storefront's committed `/products/…` files (see MediaUrl).
     */
    public static function hasManagedImages(Product $product): bool
    {
        $images = $product->images ?? [];

        if ($images === []) {
            return false;
        }

        foreach ($images as $image) {
            if (str_starts_with($image, '/products/') || str_starts_with($image, 'products/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * One InventoryItem per colour × size.
     *
     * The client's sheets give one size range per style, so every colourway
     * is seeded across the whole range; one a colour isn't made in simply
     * stays at 0 in admin. A style with no colours gets size-only rows.
     *
     * An existing row keeps its quantity: re-running against a database where
     * the brand has entered real stock must not reset it.
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
