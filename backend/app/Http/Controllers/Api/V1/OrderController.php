<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * The signed-in customer's own order history, for /account/orders.
     *
     * Scoped to `$request->user()` and nothing else — there is no customer_id
     * parameter to tamper with, because the only safe answer to "whose orders?"
     * is "the session's".
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('items.product')
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * Looked up by `reference`, never by the numeric id.
     *
     * Guest checkout means this cannot sit behind auth, and an order carries a
     * name, an email and a home address. A sequential id would let anyone walk
     * the order table by counting; the reference is random enough that guessing
     * one is not a practical attack, and the route is rate-limited on top.
     *
     * The confirmation page polls this while a freshly-placed order is still
     * `pending`, because the gateway webhook can land after the customer is
     * redirected back (README Feature 4 edge case).
     */
    public function show(string $reference): OrderResource
    {
        $order = Order::query()
            ->with('items.product')
            ->where('reference', $reference)
            ->firstOrFail();

        return new OrderResource($order);
    }
}
