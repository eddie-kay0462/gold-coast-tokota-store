<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GiftCardUnusableException;
use App\Exceptions\NoFxRateException;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGiftCardPurchaseRequest;
use App\Http\Resources\OrderResource;
use App\Services\GiftCards\GiftCardCheckout;
use App\Services\GiftCards\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The storefront's side of gift cards: what's on sale, buying one, and
 * checking what's left on a code. Spending one happens in checkout.
 */
class GiftCardController extends Controller
{
    public function __construct(private readonly GiftCardService $giftCards) {}

    public function settings(): JsonResponse
    {
        $settings = $this->giftCards->settings();

        return response()->json(['data' => [
            // On sale: the /gift-cards page offers the amounts below.
            'enabled' => $settings['enabled'] && $settings['amounts'] !== [],
            'amounts' => $settings['amounts'],
            'expiry_months' => $settings['expiry_months'],
            'terms' => $settings['terms'],
            // Checkout shows a code field: true whenever a card exists to spend.
            'redemption_open' => $this->giftCards->redemptionOpen(),
        ]]);
    }

    /** POST, so the code stays out of URLs and access logs. */
    public function balance(Request $request): JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:32']])['code'];

        try {
            $card = $this->giftCards->usable($code);
        } catch (GiftCardUnusableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['code' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => [
            'last_four' => $card->lastFour(),
            'balance' => $card->balance,
            'currency' => 'GHS',
            'expires_at' => $card->expires_at,
        ]]);
    }

    public function purchase(StoreGiftCardPurchaseRequest $request, GiftCardCheckout $checkout): JsonResponse
    {
        if (! $this->giftCards->settings()['enabled']) {
            return response()->json(['message' => 'Gift cards aren’t on sale right now.'], 409);
        }

        $data = $request->validated();
        $data['customer_id'] = $request->user()?->id;

        try {
            ['order' => $order, 'session' => $session] = $checkout->start($data);
        } catch (PaymentUnavailableException) {
            return response()->json([
                'message' => 'Card and MoMo payment is briefly unavailable. Please try again shortly.',
            ], 503);
        } catch (NoFxRateException) {
            return response()->json([
                'message' => 'Dollar checkout is briefly unavailable. Please try again shortly, or switch to cedis.',
            ], 503);
        }

        return response()->json([
            'data' => new OrderResource($order->load('items')),
            'payment' => $session->toArray(),
        ], 201);
    }
}
