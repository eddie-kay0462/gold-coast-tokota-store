<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Shipments — Admin and Staff.
 *
 * Not a separate model, and deliberately so: a shipment *is* an order that has
 * been paid for and has somewhere to go. `delivery_provider`,
 * `delivery_reference` and the shipping address already live on `orders`, and a
 * parallel table would be two records that can disagree about the same parcel.
 *
 * If Feature 5 later needs part-shipments — one order in two boxes — that is
 * when a shipments table earns its place. Not before.
 */
class ShipmentController extends Controller
{
    /** Statuses where a parcel is real: paid for, and not cancelled or refunded. */
    private const SHIPPABLE = ['paid', 'processing', 'shipped', 'delivered'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $shipments = Order::query()
            ->with(['customer', 'items'])
            ->whereIn('status', self::SHIPPABLE)
            ->when($request->filled('provider'), fn ($q) => $q->where('delivery_provider', $request->string('provider')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            // Awaiting dispatch first — the rows that need someone to do
            // something are the ones worth putting at the top.
            ->orderByRaw("CASE WHEN status IN ('paid', 'processing') THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return AdminOrderResource::collection($shipments);
    }
}
