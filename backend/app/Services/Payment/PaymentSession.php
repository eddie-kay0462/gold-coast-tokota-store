<?php

namespace App\Services\Payment;

/**
 * What a gateway hands back once a payment session is open.
 *
 * The two gateways return structurally different things — Paystack a URL to
 * redirect to, Stripe a client secret to confirm in the browser — so both are
 * nullable and the storefront branches on currency, exactly as
 * `CheckoutPaymentStep` already documents.
 */
readonly class PaymentSession
{
    public function __construct(
        public string $gateway,
        /** The gateway's own reference, stored on the order for webhook matching. */
        public string $reference,
        public ?string $authorizationUrl = null,
        public ?string $clientSecret = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'reference' => $this->reference,
            'authorization_url' => $this->authorizationUrl,
            'client_secret' => $this->clientSecret,
        ];
    }
}
