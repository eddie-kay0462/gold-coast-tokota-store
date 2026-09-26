<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Products as the admin catalogue table needs them — deliberately not the
 * storefront's ProductResource.
 *
 * Three real differences, not stylistic ones:
 *   1. **Money is `{ amount, currency }`**, matching the admin app's `Money`
 *      type, which makes the pair inseparable so a bare number can never be
 *      mistaken for a price. Same reasoning as AdminOrderResource.
 *   2. **No `price_usd`.** The storefront resource derives dollars from the
 *      cached FX rate on every read; the admin product screens compute it
 *      themselves at render time via `usdFrom()` so the editable cedi field
 *      and the read-only dollar figure move together as the user types. A
 *      server-side USD here would be a second source of truth for a number
 *      README Feature 2 forbids storing at all.
 *   3. **Stock is rolled up, not per-variant.** The table shows one row per
 *      product; per-variant detail is what `GET /admin/inventory` is for, and
 *      the product detail screen already fetches it separately.
 *
 * `category_name` is denormalised onto the row for the same reason
 * InventoryItemResource denormalises `product_name`: every row in the table
 * names its category, and a nested object would make sorting on it awkward.
 */
class AdminProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,

            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'collection_id' => $this->collection_id,
            'collection_name' => $this->whenLoaded('collection', fn () => $this->collection?->name),

            'base_price_ghs' => $this->money($this->base_price_ghs),
            'compare_at_ghs' => $this->compare_at_ghs ? $this->money($this->compare_at_ghs) : null,

            'images' => $this->images ?? [],
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_pre_order' => $this->is_pre_order,
            'is_returnable' => $this->is_returnable,
            'merchandising_badge' => $this->merchandising_badge,

            // Rolled up across variants for the list view. `total_available` is
            // raw physical stock and `total_reserved` the units held by
            // in-progress checkouts — shown side by side because "12 in stock,
            // 11 spoken for" is a very different operational picture from
            // "12 in stock", the same call InventoryItemResource makes.
            'total_available' => $this->whenLoaded(
                'inventoryItems',
                fn () => (int) $this->inventoryItems->sum('quantity_available'),
            ),
            'total_reserved' => $this->whenLoaded(
                'inventoryItems',
                fn () => (int) $this->inventoryItems->sum('quantity_reserved'),
            ),
            // True when *any* variant is at or below its own threshold, rather
            // than comparing summed totals: a product with 60 units of size 42
            // and none of size 39 needs a restock, and a rolled-up comparison
            // would hide that. Matches the per-variant test the Inventory
            // screen's low_stock filter applies.
            'low_stock' => $this->whenLoaded(
                'inventoryItems',
                fn () => $this->inventoryItems->contains(
                    fn ($item) => $item->quantity_available <= $item->low_stock_threshold,
                ),
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Products are priced in cedis and only in cedis — the currency is a
     * constant here, unlike an order, which carries the currency it was
     * actually placed in.
     *
     * @return array{amount: int, currency: string}
     */
    private function money(int $amount): array
    {
        return ['amount' => $amount, 'currency' => 'GHS'];
    }
}
