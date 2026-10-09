<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryProvider;

/**
 * International delivery (README Feature 5).
 *
 * As with YangoService, these are static rates standing in for a live DHL
 * quote until credentials exist. No free-shipping threshold: the storefront
 * only ever promises free delivery on Ghana orders.
 */
class DhlService implements DeliveryProvider
{
    private const STANDARD = 35_000;  // ₵350
    private const EXPRESS = 60_000;   // ₵600

    public function name(): string
    {
        return 'dhl';
    }

    public function quote(array $shippingAddress, string $method, int $subtotalGhs): int
    {
        return $method === 'express' ? self::EXPRESS : self::STANDARD;
    }
}
