<?php

namespace App\Contracts;

use App\Models\Order;
use App\Services\Payment\PaymentSession;

/**
 * A payment provider, selected at runtime by the order's currency
 * (README Feature 4): GHS routes to Paystack, USD to Stripe, never crossed.
 *
 * The interface exists so checkout can be built and tested end to end without
 * either provider's credentials — see FakeGateway.
 */
interface PaymentGateway
{
    /** Gateway identifier stored on the order: 'paystack', 'stripe', 'fake'. */
    public function name(): string;

    /**
     * Open a payment session for an order that has already been priced,
     * FX-locked and inventory-reserved. Implementations must not mutate the
     * order or touch inventory.
     */
    public function createSession(Order $order): PaymentSession;
}
