<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingDataMissingException;
use App\Exceptions\UnavailableProductException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShippingQuoteRequest;
use App\Services\Shipping\CheckoutShipping;
use App\Services\Shipping\ShippingQuotePresenter;
use Illuminate\Http\JsonResponse;

/**
 * Delivery options and prices for a cart, shown at checkout's delivery step
 * before anything is paid. The quote id it returns is what the checkout
 * session charges against.
 */
class ShippingQuoteController extends Controller
{
    public function __construct(private readonly CheckoutShipping $shipping) {}

    public function store(StoreShippingQuoteRequest $request): JsonResponse
    {
        try {
            ['id' => $id, 'quote' => $quote] = $this->shipping->quoteItems(
                $request->checkoutItems(),
                $request->validated('shipping_address'),
            );
        } catch (RegionNotServedException $e) {
            return ShippingQuotePresenter::regionNotServed($e);
        } catch (ShippingDataMissingException $e) {
            return ShippingQuotePresenter::dataMissing($e);
        } catch (UnavailableProductException $e) {
            return response()->json([
                'message' => 'One of the items in your order is no longer available.',
                'inventory_item_id' => $e->inventoryItemId,
            ], 409);
        }

        return response()->json(['data' => ShippingQuotePresenter::present($id, $quote)]);
    }
}
