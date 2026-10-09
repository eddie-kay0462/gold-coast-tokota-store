<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sellable variant, for the admin Inventory table (Feature 3).
 *
 * `product_name` and `sku` are denormalised onto the row on purpose: the admin
 * table lists variants, not products, and every row has to name the thing it
 * belongs to. The admin app normalises snake_case to camelCase itself
 * (admin/composables/useAdminApi.ts), so these land as `productName`/`sku` on
 * the `InventoryItem` type without anything else changing.
 */
class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product->name),
            'sku' => $this->whenLoaded('product', fn () => $this->product->sku),
            'variant_attributes' => (object) $this->variant_attributes,
            'quantity_available' => $this->quantity_available,
            'quantity_reserved' => $this->quantity_reserved,
            // What is actually purchasable right now. Admin sees this alongside
            // the raw counts because "12 in stock, 11 spoken for" is a very
            // different operational picture from "12 in stock".
            'sellable_quantity' => $this->sellable_quantity,
            'reservation_expires_at' => $this->reservation_expires_at,
            'low_stock_threshold' => $this->low_stock_threshold,
            'updated_at' => $this->updated_at,
        ];
    }
}
