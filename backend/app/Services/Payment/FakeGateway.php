<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Stands in for Paystack until its credentials exist.
 *
 * This is not a mock confined to the test suite — it is what
 * `PaymentGatewayFactory` resolves in any environment where the real key is
 * absent, so the whole checkout path (pricing, FX lock, reservation, order
 * creation, session response) can be exercised for real while
 * `PAYSTACK_SECRET_KEY` is still empty.
 *
 * It never moves money and never confirms anything: an order it opens stays
 * `pending` until a webhook says otherwise, which is exactly how the real
 * gateway behaves. That is the point — nothing downstream can accidentally
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
            // Shaped like the real thing, which since §13 settled the gateway
            // question means a redirect URL in both currencies — Paystack has
            // no client secret to confirm in the browser.
            authorizationUrl: url("/fake-gateway/{$reference}"),
            clientSecret: null,
        );
    }
}
