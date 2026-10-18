<?php

namespace App\Services\Shipping;

use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingDataMissingException;
use Illuminate\Http\JsonResponse;

/**
 * A quote as the storefront sees it. Customer-facing, so the carrier's raw
 * figure and the buffer stay on the server; the order keeps them for admin.
 */
final class ShippingQuotePresenter
{
    /** @return array<string, mixed> */
    public static function present(string $quoteId, QuoteResult $quote): array
    {
        return [
            'quote_id' => $quoteId,
            'zone' => $quote->zone->value,
            'options' => array_map(fn (QuoteOption $option) => [
                'id' => $option->id,
                'carrier' => $option->carrier,
                'label' => $option->label,
                'amount_ghs' => $option->amountGhs,
                'free' => $option->free,
                'eta' => $option->eta,
            ], $quote->options),
            // Duties the courier collects on arrival. Shown, never charged:
            // §8 makes them the customer's responsibility.
            'landed_cost' => $quote->landedCost,
        ];
    }

    /** 422 naming the products that can't go abroad yet. */
    public static function dataMissing(ShippingDataMissingException $e): JsonResponse
    {
        return response()->json([
            'message' => 'Some items in your cart can’t be shipped abroad yet. Message us on WhatsApp and we’ll arrange it.',
            'blocked_items' => $e->products,
        ], 422);
    }

    /** 422 on the region field, so the form can point at it. */
    public static function regionNotServed(RegionNotServedException $e): JsonResponse
    {
        $message = $e->region
            ? "We don't have a delivery rate for {$e->region} yet. Message us on WhatsApp and we'll arrange it."
            : 'Choose your region so we can work out delivery.';

        return response()->json([
            'message' => $message,
            'errors' => ['shipping_address.region' => [$message]],
        ], 422);
    }
}
