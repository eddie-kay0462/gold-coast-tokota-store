<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Stands in for Paystack and Stripe until their credentials exist.
 *
 * This is not a mock confined to the test suite — it is what
 * `PaymentGatewayFactory` resolves in any environment where the real keys are
 * absent, so the whole checkout path (pricing, FX lock, reservation, order
 * creation, session response) can be exercised for real while
 * `PAYSTACK_SECRET_KEY` and `STRIPE_SECRET_KEY` are still empty.
 *
 * It never moves money and never confirms anything: an order it opens stays
 * `pending` until a webhook says otherwise, which is exactly how the real
 * gateways behave. That is the point — nothing downstream can accidentally
 * come to depend on a fake payment having "succeeded".
 */
class FakeGateway implements PaymentGateway
{
    public function __construct(private readonly string $simulating) {}

    public function name(): string
    {
        return $this->simulating;
    }

    public function createSession(Order $order): PaymentSession
    {
        $reference = 'fake_'.Str::lower(Str::random(24));

        return new PaymentSession(
            gateway: $this->simulating,
            reference: $reference,
            // Shaped like the real thing so the storefront's branching can be
            // written and tested now rather than after the keys land.
            authorizationUrl: $order->currency === 'GHS'
                ? url("/fake-gateway/{$reference}")
                : null,
            clientSecret: $order->currency === 'USD' ? "{$reference}_secret" : null,
        );
    }
}
