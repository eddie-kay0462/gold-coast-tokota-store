<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
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
            ->with(['customer', 'items'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
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
            ->with(['customer', 'items'])
            ->where('reference', $reference)
            ->firstOrFail();

        return new AdminOrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $reference): AdminOrderResource
    {
        $order = Order::query()->where('reference', $reference)->firstOrFail();
        $status = $request->validated('status');
        $wasShipped = $order->status === 'shipped';

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
        if ($status === 'shipped' && ! $wasShipped) {
            SendOrderNotification::dispatch($order, 'shipped');
        }

        return new AdminOrderResource($order->load(['customer', 'items']));
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
