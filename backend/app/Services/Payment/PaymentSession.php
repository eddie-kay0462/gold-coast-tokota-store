<?php

namespace App\Services\Payment;

/**
 * What a gateway hands back once a payment session is open.
 *
 * `clientSecret` is always null now. It existed for Stripe, which §13 of the
 * brand document does not name — Paystack is the only gateway, and it hands
 * back a URL to redirect to in either currency. The field stays because a
 * second gateway would need it again and removing it would break the
 * storefront's response shape for nothing.
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
