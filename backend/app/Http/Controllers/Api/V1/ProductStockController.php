<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * Live stock for one product (Feature 3), polled by the storefront's
 * `useInventoryPolling` every 15-30s while a product detail page is open.
 *
 * This endpoint exists purely to keep the *displayed* Add-to-Cart state fresh.
 * Correctness is enforced server-side at checkout by
 * InventoryReservationService's row-level locking, which holds regardless of
 * how stale this response is by the time someone acts on it — so this is
 * deliberately a plain read with no locking and no reservation side effects.
 */
class ProductStockController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->active()
            ->with('inventoryItems')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'slug' => $product->slug,

                // Sellable, not the raw quantity_available column: a unit held
                // by someone else's in-progress payment is not purchasable, and
                // offering it is worse than showing it as gone. The key keeps
                // the frontend's existing name (useInventoryPolling reads
                // `quantity_available`) — the storefront's sense of "available"
                // has always been "available to buy".
                'quantity_available' => $product->inventoryItems->sum('sellable_quantity'),

                // Per-size, which is what ProductPurchasePanel actually needs to
                // strike through individual sizes. The polling composable only
                // reads the aggregate today; this is here so wiring the panel to
                // live stock doesn't need another round trip to the API.
                'size_availability' => (object) $product->size_availability,

                'in_stock' => $product->in_stock,
                'merchandising_badge' => $product->effective_badge,
            ],
        ]);
    }
}
