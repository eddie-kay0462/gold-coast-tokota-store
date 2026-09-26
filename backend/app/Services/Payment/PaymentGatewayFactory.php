<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the gateway for an order.
 *
 * **This used to route GHS to Paystack and USD to Stripe.** §13 of the brand
 * document names one payment gateway — Paystack — settling in Ghana Cedis and
 * accepting Visa, Mastercard, Verve, mobile money and bank transfer; §22.12
 * says the same in one line. Nothing in the document mentions Stripe, and a
 * dollar card payment is a card payment, so both currencies now route to
 * Paystack and the currency split is gone.
 *
 * Two consequences worth knowing:
 *   - A USD checkout no longer returns a `client_secret`. It returns an
 *     authorization URL, exactly as a cedi checkout does — the storefront's
 *     currency branch at the payment step has one branch too many now.
 *   - Settlement is in GHS whatever the customer was charged in, which is what
 *     §13 says and what the FX lock on the order already assumes.
 */
class PaymentGatewayFactory
{
    /** The currencies the storefront can check out in (§19). */
    private const SUPPORTED_CURRENCIES = ['GHS', 'USD'];

    public function for(string $currency): PaymentGateway
    {
        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new \InvalidArgumentException(
                "No payment gateway is configured for currency [{$currency}]."
            );
        }

        if (PaystackService::isConfigured()) {
            return new PaystackService;
        }

        // No key, so every environment gets the fake — and says so. An API
        // that looks like it is taking card payments and is not should be
        // noisy about it, not quiet.
        Log::info('No Paystack secret key configured; using FakeGateway.', [
            'currency' => $currency,
        ]);

        return new FakeGateway('paystack');
    }
}
