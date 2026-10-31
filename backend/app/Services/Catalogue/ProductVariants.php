<?php

namespace App\Services\Catalogue;

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A product's sizes and colours, as set in admin.
 *
 * Stock is one inventory row per colour and size (or per size, for a product
 * with no colourways), the same grid `ProductUpserter` builds from the
 * catalogue. Setting the lists makes the rows match: a new combination gets a
 * row at zero, and a combination no longer listed loses its row.
 *
 * Removing is where stock could be lost, so it is guarded twice. A row with
 * units held for a checkout in progress is never removed (that order would
 * have nothing to take). A row with stock on the shelf is removed only when
 * the caller says so (`$removeStocked`), so the admin is asked before a count
 * disappears.
 */
final class ProductVariants
{
    /** New rows start empty: the brand enters real counts in Inventory. */
    public const LOW_STOCK_THRESHOLD = 2;

    /**
     * @param  list<string>  $sizes
     * @param  list<array{name: string, hex?: string|null}>  $colours
     */
    public function sync(Product $product, array $sizes, array $colours, bool $removeStocked = false): Product
    {
        $sizes = array_values(array_unique(array_map(fn ($s) => trim((string) $s), $sizes)));
        $colours = collect($colours)
            ->map(fn (array $c) => ['name' => trim($c['name']), 'hex' => isset($c['hex']) && $c['hex'] !== '' ? strtoupper($c['hex']) : null])
            ->unique(fn (array $c) => mb_strtolower($c['name']))
            ->values()
            ->all();
        $colourNames = array_column($colours, 'name');

        return DB::transaction(function () use ($product, $sizes, $colours, $colourNames, $removeStocked) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            $wanted = [];
            foreach ($colourNames ?: [null] as $colour) {
                foreach ($sizes as $size) {
                    $wanted[self::key($size, $colour)] = ['size' => $size, 'colour' => $colour];
                }
            }

            $rows = InventoryItem::query()->where('product_id', $product->id)->lockForUpdate()->get();
            $have = $rows->keyBy(fn (InventoryItem $row) => self::key(
                (string) ($row->variant_attributes['size'] ?? ''),
                $row->variant_attributes['colour'] ?? null,
            ));

            $leaving = $have->reject(fn ($row, $key) => isset($wanted[$key]));
            $this->refuseUnsafeRemovals($leaving, $removeStocked);

            InventoryItem::query()->whereIn('id', $leaving->pluck('id'))->delete();

            foreach ($wanted as $key => $attrs) {
                if ($have->has($key)) {
                    continue;
                }
                InventoryItem::query()->create([
                    'product_id' => $product->id,
                    'variant_attributes' => array_filter($attrs, fn ($v) => $v !== null),
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                ]);
            }

            // A colourway that's gone takes its photo grouping with it; the
            // photos themselves stay in the gallery until someone removes them.
            $product->forceFill([
                'colors' => $colours,
                'colour_images' => array_intersect_key($product->colour_images ?? [], array_flip($colourNames)),
            ])->save();

            return $product;
        });
    }

    /** @param  Collection<string, InventoryItem>  $leaving */
    private function refuseUnsafeRemovals($leaving, bool $removeStocked): void
    {
        $held = $leaving->filter(fn (InventoryItem $row) => $row->quantity_reserved > 0
            || StockHold::query()->active()->where('inventory_item_id', $row->id)->exists());

        if ($held->isNotEmpty()) {
            throw ValidationException::withMessages([
                'sizes' => 'Can’t remove '.self::describe($held).': it has stock held for an order in progress. Try again once that order is paid or lapses.',
            ]);
        }

        $stocked = $leaving->filter(fn (InventoryItem $row) => $row->quantity_available > 0);

        if ($stocked->isNotEmpty() && ! $removeStocked) {
            throw ValidationException::withMessages([
                'remove_stocked' => 'Removing '.self::describe($stocked).' also removes '
                    .$stocked->sum('quantity_available').' in stock. Confirm to go ahead.',
            ]);
        }
    }

    /** @param  Collection<string, InventoryItem>  $rows */
    private static function describe($rows): string
    {
        return $rows->map(function (InventoryItem $row) {
            $attrs = $row->variant_attributes;

            return trim('size '.($attrs['size'] ?? '?').(isset($attrs['colour']) ? " in {$attrs['colour']}" : ''));
        })->join(', ', ' and ');
    }

    private static function key(string $size, ?string $colour): string
    {
        return mb_strtolower(($colour ?? '').'|'.$size);
    }
}
