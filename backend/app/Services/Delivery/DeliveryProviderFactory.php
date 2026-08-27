<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryProvider;

/**
 * Routes an order to its courier on destination alone (README Feature 5):
 * a Ghana address goes to Yango, anything else to DHL. The acceptance
 * criterion is that Ghana orders never generate a DHL booking and vice versa,
 * so this is the single place the decision is made.
 */
class DeliveryProviderFactory
{
    /** @param  array<string, mixed>  $shippingAddress */
    public function for(array $shippingAddress): DeliveryProvider
    {
        $country = strtoupper((string) ($shippingAddress['country'] ?? ''));

        return $country === 'GH' ? new YangoService : new DhlService;
    }
}
