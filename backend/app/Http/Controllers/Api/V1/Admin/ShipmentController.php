<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualDispatchRequest;
use App\Http\Requests\Admin\MarkShipmentFailedRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Http\Resources\Admin\ShipmentResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

/**
 * Shipments — Admin and Staff.
 *
 * The list is still a view over orders, because the screen is a queue of
 * parcels to send and an order is the parcel. What changed with the shipping
 * module is that each order now carries its booking — carrier, tracking,
 * history — on a `shipments` row, since one order can need several bookings
 * (a retry, a carrier hand-over) and a parcel's history needed somewhere to
 * live.
 */
class ShipmentController extends Controller
{
    /** Statuses where a parcel is real: paid for, and not cancelled or refunded. */
    private const SHIPPABLE = ['paid', 'processing', 'shipped', 'delivered'];

    public function __construct(private readonly ShipmentService $shipments) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $shipments = Order::query()
            ->with(['customer', 'items', 'shipment'])
            ->whereIn('status', self::SHIPPABLE)
            ->when($request->filled('provider'), fn ($q) => $q->where('delivery_provider', $request->string('provider')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('attention'), fn ($q) => $this->needingAttention($q))
            // Awaiting dispatch first — the rows that need someone to do
            // something are the ones worth putting at the top.
            ->orderByRaw("CASE WHEN status IN ('paid', 'processing') THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return AdminOrderResource::collection($shipments);
    }

    /** How many orders need a person, for the admin badge. */
    public function attention(): JsonResponse
    {
        return response()->json(['data' => [
            'count' => $this->needingAttention(Order::query()->whereIn('status', self::SHIPPABLE))->count(),
        ]]);
    }

    /**
     * Orders whose live shipment has failed, or has sat waiting for longer
     * than the 48 hours §21 allows for processing.
     *
     * @param  Builder<Order>  $orders
     * @return Builder<Order>
     */
    private function needingAttention(Builder $orders): Builder
    {
        return $orders->whereHas('shipment', fn ($shipment) => $shipment->where(fn ($q) => $q
            ->where('status', ShipmentStatus::Failed->value)
            ->orWhere(fn ($stale) => $stale
                ->where('status', ShipmentStatus::Pending->value)
                ->where('created_at', '<', now()->subHours((int) config('shipping.ops.stale_pending_hours'))))));
    }

    /**
     * A courier label or customs invoice, as a short-lived signed link. They
     * live in the private bucket because they carry the customer's name and
     * address.
     */
    public function document(Shipment $shipment, string $kind): JsonResponse
    {
        $path = match ($kind) {
            'label' => $shipment->label_path,
            'invoice' => $shipment->invoice_path,
            default => null,
        };

        abort_if($path === null, 404);

        return response()->json(['data' => ['url' => MediaStorage::privateUrl($path, 10)]]);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        return new ShipmentResource($shipment->load('events'));
    }

    /**
     * The parcel is packed. For Yango (and later DHL) this books the courier;
     * for a manual shipment it is only recorded. Safe to press twice.
     */
    public function pack(Request $request, Shipment $shipment): JsonResponse
    {
        try {
            $this->shipments->markPacked($shipment, $request->user('admin'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new ShipmentResource($shipment->fresh()->load('events')))->response()->setStatusCode(200);
    }

    /**
     * A closed shipment cannot be marked again, and a courier booking still
     * being arranged cannot be marked by hand: the booking is live and could
     * still send a courier. Once a courier has the parcel, staff may record
     * that it arrived or was lost.
     */
    private function refuseManualMark(Shipment $shipment): ?JsonResponse
    {
        if ($shipment->status->isTerminal()) {
            return response()->json(['message' => 'This shipment is already closed.'], 422);
        }

        if ($shipment->carrier !== 'manual' && in_array($shipment->status, [ShipmentStatus::Pending, ShipmentStatus::Booking], true)) {
            return response()->json([
                'message' => "This {$shipment->carrier} booking is still being arranged. Wait for a courier to be assigned, or for it to fail.",
            ], 422);
        }

        return null;
    }

    public function manualDispatch(ManualDispatchRequest $request, Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->shipments->dispatchManually($shipment, $request->validated(), $request->user('admin'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // 200 even when a failed booking was superseded by a new manual row:
        // the action is "dispatch this order", whichever row records it.
        return (new ShipmentResource($shipment->load('events')))->response()->setStatusCode(200);
    }

    public function markDelivered(Request $request, Shipment $shipment): ShipmentResource|JsonResponse
    {
        if ($refusal = $this->refuseManualMark($shipment)) {
            return $refusal;
        }

        $shipment = $this->shipments->markDelivered($shipment, $request->user('admin'));

        return new ShipmentResource($shipment->load('events'));
    }

    public function markFailed(MarkShipmentFailedRequest $request, Shipment $shipment): ShipmentResource|JsonResponse
    {
        if ($refusal = $this->refuseManualMark($shipment)) {
            return $refusal;
        }

        $shipment = $this->shipments->markFailed($shipment, $request->validated('reason'), $request->user('admin'));

        return new ShipmentResource($shipment->load('events'));
    }
}
