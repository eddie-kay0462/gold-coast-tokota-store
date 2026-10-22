<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\NoFxRateException;
use App\Exceptions\PaymentUnavailableException;
use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingDataMissingException;
use App\Exceptions\ShippingOptionChangedException;
use App\Exceptions\UnavailableProductException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWhatsAppOrderRequest;
use App\Http\Resources\Admin\ChatMessageResource;
use App\Models\Order;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Payment\PaymentSession;
use App\Services\Shipping\ShippingQuotePresenter;
use App\Services\WhatsApp\WhatsAppOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders built in the WhatsApp inbox: the builder's starting point, placing
 * the order (which sends the payment link), resending a link, cancelling.
 *
 * A checkout failure answers exactly as the storefront checkout does (409
 * for stock or delivery that changed, 503 when payment is unavailable), so
 * the builder can show the same messages.
 */
class WhatsAppOrderController extends Controller
{
    public function __construct(private readonly WhatsAppOrders $orders) {}

    public function builder(WhatsAppConversation $thread): JsonResponse
    {
        return response()->json(['data' => $this->orders->builderFor($thread)]);
    }

    public function store(StoreWhatsAppOrderRequest $request, WhatsAppConversation $thread): JsonResponse
    {
        $data = $request->validated();
        $data['items'] = $request->checkoutItems();
        $thread->load('contact');

        try {
            $result = $this->orders->place($thread, $data, $request->user('admin'));
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => "Only {$e->inventoryItem->sellable_quantity} of one of these items can be sold right now.",
                'inventory_item_id' => $e->inventoryItem->id,
                'available' => $e->inventoryItem->sellable_quantity,
            ], 409);
        } catch (UnavailableProductException $e) {
            return response()->json([
                'message' => 'One of these items is no longer on sale.',
                'inventory_item_id' => $e->inventoryItemId,
            ], 409);
        } catch (RegionNotServedException $e) {
            return ShippingQuotePresenter::regionNotServed($e);
        } catch (ShippingDataMissingException $e) {
            return ShippingQuotePresenter::dataMissing($e);
        } catch (ShippingOptionChangedException $e) {
            return response()->json([
                'message' => 'The delivery price has changed. Check the new price and place the order again.',
                'shipping_quote' => ShippingQuotePresenter::present($e->quoteId, $e->quote),
            ], 409);
        } catch (PaymentUnavailableException) {
            return response()->json(['message' => 'Paystack is briefly unavailable, so no payment link could be made. Nothing was held. Try again shortly.'], 503);
        } catch (NoFxRateException) {
            return response()->json(['message' => 'There is no current dollar rate, so a USD order can’t be priced right now. Try again shortly, or price it in cedis.'], 503);
        }

        return $this->result($result, 201);
    }

    public function resendLink(Request $request, Order $order): JsonResponse
    {
        try {
            $result = $this->orders->resendLink($order, $request->user('admin'));
        } catch (PaymentUnavailableException) {
            return response()->json(['message' => 'Paystack is briefly unavailable, so no new link could be made. Try again shortly.'], 503);
        }

        return $this->result($result, 200);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        return response()->json(['data' => $this->orders->summary($this->orders->cancel($order, $request->user('admin')))]);
    }

    /** @param  array{order: Order, session: PaymentSession, message: ?WhatsAppMessage, warning: ?string}  $result */
    private function result(array $result, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'order' => $this->orders->summary($result['order']),
                'payment_url' => $result['session']->authorizationUrl,
                'message' => $result['message'] ? new ChatMessageResource($result['message']->load('sentBy:id,name')) : null,
                'warning' => $result['warning'],
            ],
        ], $status);
    }
}
