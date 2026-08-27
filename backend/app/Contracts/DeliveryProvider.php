<?php

namespace App\Contracts;

/**
 * A courier, selected by destination (README Feature 5): Ghana routes to
 * Yango, everywhere else to DHL. Never crossed.
 */
interface DeliveryProvider
{
    /** Provider identifier stored on the order: 'yango' or 'dhl'. */
    public function name(): string;

    /**
     * Shipping cost in GHS minor units, before any currency conversion.
     *
     * Quoted before payment, never after: the README is explicit that the
     * figure shown at checkout is the figure charged.
     *
     * @param  array<string, mixed>  $shippingAddress
     * @param  'standard'|'express'  $method
     * @param  int  $subtotalGhs  Goods subtotal, for free-shipping thresholds.
     */
    public function quote(array $shippingAddress, string $method, int $subtotalGhs): int;
}
