<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Admin Orders (README Feature 9) — Admin and Staff. Refunds are gated to
 * Admin inside UpdateOrderStatusRequest, since that depends on the status
 * being asked for rather than on the route.
 */
class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['customer', 'items', 'shipment'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->when(
                in_array($request->query('channel'), ['web', 'whatsapp'], true),
                fn ($query) => $query->where('channel', $request->query('channel')),
            )
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.mb_strtolower(trim((string) $request->string('q'))).'%';

                // Reference, customer name and customer email — the three
                // things someone has in front of them when a customer gets in
                // touch. Guests have no Customer row, so the shipping address
                // is searched too, or half the orders would be unfindable.
                //
                // LOWER(...) LIKE rather than Postgres's ILIKE: production is
                // Postgres but the test suite runs on SQLite, and ILIKE only
                // exists on one of them. A search that works in only one of
                // the two environments is worse than a slightly longer clause.
                $query->where(function ($scoped) use ($term) {
                    $scoped
                        ->whereRaw('LOWER(reference) LIKE ?', [$term])
                        ->orWhereRaw('LOWER('.$this->jsonPath('shipping_address', 'full_name').') LIKE ?', [$term])
                        ->orWhereRaw('LOWER('.$this->jsonPath('shipping_address', 'email').') LIKE ?', [$term])
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]));
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return AdminOrderResource::collection($orders);
    }

    public function show(string $reference): AdminOrderResource
    {
        $order = Order::query()
            ->with(['customer', 'items', 'shipment.events'])
            ->where('reference', $reference)
            ->firstOrFail();

        return new AdminOrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $reference): AdminOrderResource|JsonResponse
    {
        $order = Order::query()->where('reference', $reference)->firstOrFail();
        $status = $request->validated('status');

        // Once an order has a shipment, `shipped` and `delivered` are facts
        // about the parcel, recorded on the shipment — the order follows it.
        // Setting them here as well would let the two disagree.
        if (in_array($status, ['shipped', 'delivered'], true) && $order->shipment()->exists()) {
            return response()->json([
                'message' => 'Record dispatch and delivery on the order\'s shipment; the order status follows it.',
            ], 422);
        }
        $wasShipped = $order->status === 'shipped';
        $wasPending = $order->status === 'pending';

        $order->update([
            'status' => $status,
            // §9 runs the returns window from the day the order is *received*,
            // so the moment it is marked delivered is the moment that clock
            // starts. Set once and kept: a correction back to `shipped` and
            // forward again should not quietly extend a customer's window.
            'delivered_at' => $status === 'delivered'
                ? ($order->delivered_at ?? now())
                : $order->delivered_at,
        ]);

        // The brand document's dispatch promise: "Customers receive a
        // confirmation message and tracking information where available once
        // their order has been dispatched." On the transition into `shipped`
        // only — a correction that re-saves an already-shipped order should
        // not tell the customer it shipped twice.
        // An unpaid order called off gives back the stock and any gift card
        // balance it held, as a WhatsApp order cancelled from the inbox does,
        // rather than keeping them out of reach until the hold lapses.
        if ($status === 'cancelled' && $wasPending) {
            $by = $request->user('admin')?->name;
            $note = $by ? "Cancelled in admin by {$by}" : 'Order cancelled in admin';
            app(InventoryReservationService::class)->releaseOrder($order, $note);
            app(GiftCardService::class)->release($order, 'Order cancelled in admin');
        }

        if ($status === 'shipped' && ! $wasShipped) {
            SendOrderNotification::dispatch($order, 'shipped');
        }

        return new AdminOrderResource($order->load(['customer', 'items', 'shipment']));
    }

    /**
     * A JSON field reference that reads the same on Postgres and SQLite.
     * Postgres wants `col->>'key'`; SQLite wants json_extract(col, '$.key').
     */
    private function jsonPath(string $column, string $key): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "{$column}->>'{$key}'"
            : "json_extract({$column}, '$.{$key}')";
    }
}
