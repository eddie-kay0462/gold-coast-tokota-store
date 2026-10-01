<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Set the physical count (and/or the low-stock threshold) for one variant.
     *
     * This is how stock gets into the system at all: the catalogue is seeded
     * with its sizes but no quantities, and the brand enters the real counts
     * from the admin Inventory screen.
     *
     * Locked for the same reason a reservation is — a checkout reserving the
     * last pair and a restock landing together must not overwrite each other.
     * `quantity_reserved` is never writable here: it belongs to
     * InventoryReservationService alone.
     */
    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): InventoryItemResource
    {
        $data = $request->validated();

        $item = DB::transaction(function () use ($data, $inventoryItem) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($inventoryItem->id);

            // A count below what pending checkouts already hold would let a
            // paid order find nothing on the shelf.
            if (isset($data['quantity_available']) && $data['quantity_available'] < $locked->quantity_reserved) {
                throw ValidationException::withMessages([
                    'quantity_available' => "{$locked->quantity_reserved} of these are held by checkouts in progress, so the count cannot go below that.",
                ]);
            }

            $locked->update($data);

            return $locked;
        });

        return new InventoryItemResource($item->load('product:id,name,sku'));
    }
}
