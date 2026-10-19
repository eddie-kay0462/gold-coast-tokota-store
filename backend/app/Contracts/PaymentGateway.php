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
     *
     * `$reference` overrides the transaction reference (the order's own by
     * default). A gateway refuses a reference twice, so re-opening payment
     * for an order, e.g. resending a WhatsApp payment link whose page has
     * gone stale, needs a new one; the webhook matches either.
     */
    public function createSession(Order $order, ?string $reference = null): PaymentSession;
}
