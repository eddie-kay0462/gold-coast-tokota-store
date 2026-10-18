<?php

namespace App\Services\Shipping;

use App\Enums\ShippingZone;
use App\Models\Product;

/**
 * Everything a carrier needs to price one checkout.
 *
 * Lines carry their own physical data — weight in grams and packed size in
 * millimetres — and a carrier converts at its own edge, because Yango wants
 * kilograms and metres and DHL wants kilograms and centimetres.
 */
readonly class QuoteRequest
{
    /**
     * @param  array<string, mixed>  $address  The shipping address, with lat/lng when pinned.
     * @param  list<array{name: string, quantity: int, unit_price_ghs: int, weight_grams: int|null, length_mm: int|null, width_mm: int|null, height_mm: int|null, hs_code: string|null, origin_country: string|null, customs_description: string|null}>  $lines
     */
    public function __construct(
        public ShippingZone $zone,
        public array $address,
        public array $lines,
        public int $subtotalGhs,
    ) {}

    /**
     * Builds the request from priced cart lines. Physical and customs data
     * comes off the product; until Phase 3 adds those columns it is null,
     * and carriers that need it fall back to config('shipping.default_parcel').
     *
     * @param  array<string, mixed>  $address
     * @param  iterable<array{product: Product, quantity: int, unit_price: int}>  $lines
     */
    public static function fromLines(ShippingZone $zone, array $address, iterable $lines, int $subtotalGhs): self
    {
        $physical = [];

        foreach ($lines as $line) {
            $product = $line['product'];

            $physical[] = [
                'name' => $product->name,
                'quantity' => (int) $line['quantity'],
                'unit_price_ghs' => (int) $line['unit_price'],
                'weight_grams' => $product->getAttribute('weight_grams'),
                'length_mm' => $product->getAttribute('length_mm'),
                'width_mm' => $product->getAttribute('width_mm'),
                'height_mm' => $product->getAttribute('height_mm'),
                'hs_code' => $product->getAttribute('hs_code'),
                'origin_country' => $product->getAttribute('origin_country'),
                'customs_description' => $product->getAttribute('customs_description'),
            ];
        }

        return new self($zone, $address, $physical, $subtotalGhs);
    }

    public function lat(): ?float
    {
        return is_numeric($this->address['lat'] ?? null) ? (float) $this->address['lat'] : null;
    }

    public function lng(): ?float
    {
        return is_numeric($this->address['lng'] ?? null) ? (float) $this->address['lng'] : null;
    }

    /**
     * What makes two quotes the same quote: where it is going and what is in
     * the box. A cached quote is only honoured for an identical fingerprint,
     * so changing the cart or moving the pin always re-prices.
     */
    public function fingerprint(): string
    {
        $address = $this->address;

        return hash('sha256', json_encode([
            $this->zone->value,
            strtoupper((string) ($address['country'] ?? '')),
            mb_strtolower(trim((string) ($address['region'] ?? ''))),
            mb_strtolower(trim((string) ($address['city'] ?? ''))),
            mb_strtolower(trim((string) ($address['line1'] ?? ''))),
            mb_strtolower(trim((string) ($address['postcode'] ?? ''))),
            $this->lat() === null ? null : round($this->lat(), 5),
            $this->lng() === null ? null : round($this->lng(), 5),
            array_map(fn (array $line) => [$line['name'], $line['quantity'], $line['unit_price_ghs']], $this->lines),
            $this->subtotalGhs,
        ]));
    }
}
