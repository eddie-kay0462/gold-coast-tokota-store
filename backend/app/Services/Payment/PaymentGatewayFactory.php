<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the gateway for an order's currency (README Feature 4).
 *
 * GHS routes exclusively through Paystack and USD exclusively through Stripe —
 * the acceptance criterion is "no cross-routing", so the mapping is a match,
 * not a default with a fallback.
 */
class PaymentGatewayFactory
{
    private const GATEWAYS = [
        'GHS' => 'paystack',
        'USD' => 'stripe',
    ];

    public function for(string $currency): PaymentGateway
    {
        $gateway = self::GATEWAYS[$currency] ?? throw new \InvalidArgumentException(
            "No payment gateway is configured for currency [{$currency}]."
        );

        // PaystackService and StripeService slot in here once their
        // credentials exist — every key in `.env` for both is still empty.
        // Until then every environment gets the fake, and logs that it did:
        // an API that looks like it is taking card payments and is not should
        // be noisy about it, not quiet.
        Log::info('No live payment gateway configured; using FakeGateway.', [
            'simulating' => $gateway,
            'currency' => $currency,
        ]);

        return new FakeGateway($gateway);
    }
}
