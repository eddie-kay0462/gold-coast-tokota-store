<?php

namespace App\Services\Shipping;

use App\Exceptions\RegionNotServedException;
use App\Exceptions\ShippingOptionChangedException;
use App\Exceptions\UnavailableProductException;
use App\Models\InventoryItem;
use App\Models\Product;

/**
 * Delivery pricing as checkout sees it: quote a cart, remember the quote, and
 * later charge exactly what was quoted.
 *
 * The rule it exists to keep is README Feature 5's acceptance criterion — the
 * delivery cost shown is the delivery cost charged. A quote is cached under an
 * id the storefront sends back with the order; if that quote has expired or
 * no longer matches the cart, the order is refused with a fresh quote rather
 * than quietly charged a different amount.
 */
class CheckoutShipping
{
    public function __construct(
        private readonly ShippingManager $shipping,
        private readonly QuoteCache $quotes,
    ) {}

    /**
     * Prices delivery for resolved cart lines and caches the result.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int}>  $items
     * @param  array<string, mixed>  $address
     * @return array{id: string, quote: QuoteResult}
     *
     * @throws RegionNotServedException
     * @throws UnavailableProductException
     */
    public function quoteItems(array $items, array $address): array
    {
        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $inventoryItem = InventoryItem::query()->with('product')->findOrFail($item['inventory_item_id']);
            $product = $inventoryItem->product;

            if (! $product instanceof Product || ! $product->is_active) {
                throw new UnavailableProductException($inventoryItem->id);
            }

            $lines[] = ['product' => $product, 'quantity' => $item['quantity'], 'unit_price' => $product->base_price_ghs];
            $subtotal += $product->base_price_ghs * $item['quantity'];
        }

        $request = $this->request($address, $lines, $subtotal);
        $quote = $this->shipping->quote($request);

        return ['id' => $this->quotes->put($request, $quote), 'quote' => $quote];
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  iterable<array{product: Product, quantity: int, unit_price: int}>  $lines
     */
    public function request(array $address, iterable $lines, int $subtotalGhs): QuoteRequest
    {
        return QuoteRequest::fromLines($this->shipping->zoneFor($address), $address, $lines, $subtotalGhs);
    }

    /**
     * The option the customer chose, at the price they were shown.
     *
     * With no quote id — a client that never showed a delivery price — the
     * cart is priced now and the zone's first option taken.
     *
     * @throws RegionNotServedException
     * @throws ShippingOptionChangedException
     */
    public function choose(QuoteRequest $request, ?string $quoteId, ?string $optionId): QuoteOption
    {
        if ($quoteId !== null) {
            $cached = $this->quotes->get($quoteId, $request);
            $option = $cached && $optionId !== null ? $cached->option($optionId) : $cached?->options[0];

            if ($option !== null) {
                return $option;
            }

            $fresh = $this->shipping->quote($request);

            throw new ShippingOptionChangedException($fresh, $this->quotes->put($request, $fresh));
        }

        $quote = $this->shipping->quote($request);

        return ($optionId !== null ? $quote->option($optionId) : null) ?? $quote->options[0];
    }
}
