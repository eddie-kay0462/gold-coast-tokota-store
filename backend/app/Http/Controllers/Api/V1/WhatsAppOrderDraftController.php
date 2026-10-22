<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsAppOrderDraftRequest;
use App\Services\WhatsApp\WhatsAppOrders;
use Illuminate\Http\JsonResponse;

/**
 * The storefront's "Order on WhatsApp": saves the cart and returns a short
 * code for the WhatsApp message. Public and throttled, and holds no stock,
 * so a flood of these costs nothing but a few rows (pruned after a week).
 */
class WhatsAppOrderDraftController extends Controller
{
    public function store(StoreWhatsAppOrderDraftRequest $request, WhatsAppOrders $orders): JsonResponse
    {
        $draft = $orders->draft($request->checkoutItems(), (string) $request->validated('currency'));

        return response()->json(['data' => [
            'reference' => $draft->reference,
            'expires_at' => $draft->expires_at,
        ]], 201);
    }
}
