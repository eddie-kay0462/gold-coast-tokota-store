<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin Inventory view (Feature 3). Admin *and* Staff — restocking is
 * day-to-day operations, not a pricing decision.
 */
class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = InventoryItem::query()
            ->with('product:id,name,sku')
            ->when(
                $request->boolean('low_stock'),
                // Raw quantity_available, not sellable, and deliberately so:
                // this list answers "what do we need to make more of", which is
                // a question about physical stock. Reserved units are still on
                // the shelf. The README specifies this comparison exactly.
                fn ($query) => $query->whereColumn('quantity_available', '<=', 'low_stock_threshold'),
            )
            ->when(
                $request->filled('product_id'),
                fn ($query) => $query->where('product_id', $request->integer('product_id')),
            )
            // Scarcest first: the rows that need a decision are the ones worth
            // putting at the top of an operational table.
            ->orderBy('quantity_available')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return InventoryItemResource::collection($items);
    }
}
