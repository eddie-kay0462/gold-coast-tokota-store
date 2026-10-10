<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryProvider;

/**
 * Domestic delivery within Ghana (README Feature 5).
 *
 * The rates below are a **static table, not a live quote.** Yango credentials
 * do not exist yet, and the README explicitly permits a static/link-based
 * fallback until they do. The numbers match what the storefront already tells
 * customers — "Free shipping on all Ghana orders over ₵1,500" on the product
 * page — because a checkout that contradicts the product page is worse than
 * one that is merely approximate.
 *
 * When credentials land, only the body of quote() changes.
 */
class YangoService implements DeliveryProvider
{
    /** Free-shipping threshold in GHS minor units — ₵1,500. */
    private const FREE_SHIPPING_THRESHOLD = 150_000;

    private const STANDARD = 2_500;   // ₵25
    private const EXPRESS = 5_000;    // ₵50

    public function name(): string
    {
        return 'yango';
    }

    public function quote(array $shippingAddress, string $method, int $subtotalGhs): int
    {
        if ($method === 'express') {
            return self::EXPRESS;
        }

        // The threshold applies to standard delivery only. Someone paying for
        // express is buying speed, not distance, and giving it away above a
        // spend threshold would make express cheaper than standard.
        return $subtotalGhs >= self::FREE_SHIPPING_THRESHOLD ? 0 : self::STANDARD;
    }
}
