<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\NoFxRateException;
use App\Exceptions\UnavailableProductException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutSessionRequest;
use App\Http\Resources\OrderResource;
use App\Services\Checkout\CheckoutSessionService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutSessionService $checkout) {}

    public function store(StoreCheckoutSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Never taken from the request body: a guest checkout must not be able
        // to claim someone else's customer record.
        $data['customer_id'] = $request->user()?->id;

        try {
            ['order' => $order, 'session' => $session] = $this->checkout->create($data);
        } catch (InsufficientStockException $e) {
            // 409, not 422: the request was well-formed, the world changed
            // underneath it. The storefront's job here is to say which line
            // went and let the customer decide, not to re-validate a form.
            return response()->json([
                'message' => 'One of the items in your order has just sold out.',
                'inventory_item_id' => $e->inventoryItem->id,
                'available' => $e->inventoryItem->sellable_quantity,
            ], 409);
        } catch (UnavailableProductException $e) {
            return response()->json([
                'message' => 'One of the items in your order is no longer available.',
                'inventory_item_id' => $e->inventoryItemId,
            ], 409);
        } catch (NoFxRateException) {
            // Deliberately not falling back to a stale or default rate — an
            // order priced on a guessed rate loses real money on every unit.
            return response()->json([
                'message' => 'Dollar checkout is briefly unavailable. Please try again shortly, or switch to cedis.',
            ], 503);
        }

        return response()->json([
            'data' => new OrderResource($order->load('items.product')),
            'payment' => $session->toArray(),
        ], 201);
    }
}
