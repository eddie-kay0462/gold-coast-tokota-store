<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack — the payment gateway (§13, §22.12).
 *
 * §13 names one provider, settling in Ghana Cedis, accepting Visa, Mastercard,
 * Verve, MTN/Telecel/AirtelTigo mobile money and bank transfer. There is no
 * second gateway in the brand document, which is why USD no longer routes
 * anywhere else — see PaymentGatewayFactory.
 *
 * Amounts are sent in the currency's subunit, which is what every column in
 * this codebase already holds, so nothing is scaled on the way out. A bug
 * here is a factor of a hundred on a real charge, so the absence of arithmetic
 * is the point.
 */
class PaystackService implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public static function isConfigured(): bool
    {
        return filled(config('services.paystack.secret'));
    }

    public function createSession(Order $order): PaymentSession
    {
        $address = $order->shipping_address ?? [];

        $response = Http::withToken(config('services.paystack.secret'))
            ->acceptJson()
            ->asJson()
            // A checkout blocked on a hanging gateway should fail fast: the
            // customer is watching a spinner and the stock is reserved.
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->post($this->endpoint('/transaction/initialize'), [
                'email' => $address['email'] ?? $order->customer?->email,
                // Already minor units throughout this codebase — see the note
                // on the class. Nothing is multiplied here on purpose.
                'amount' => $order->total,
                'currency' => $order->currency,
                // Our reference, not theirs: it is what the customer quotes,
                // what the confirmation page looks up, and what the webhook
                // is matched back to.
                'reference' => $order->reference,
                'callback_url' => $this->callbackUrl($order),
                'metadata' => [
                    'order_reference' => $order->reference,
                    'order_id' => $order->id,
                ],
            ]);

        if ($response->failed() || $response->json('status') !== true) {
            throw new RuntimeException(
                'Paystack refused the transaction: '.($response->json('message') ?? $response->status())
            );
        }

        return new PaymentSession(
            gateway: $this->name(),
            reference: $response->json('data.reference') ?? $order->reference,
            authorizationUrl: $response->json('data.authorization_url'),
            // Paystack redirects; there is nothing to confirm in the browser.
            clientSecret: null,
        );
    }

    /**
     * Confirm a transaction with Paystack directly.
     *
     * The webhook signature already proves the payload came from Paystack, so
     * this is not what makes a charge trustworthy — it is what catches the
     * case where the customer returns to the callback URL before the webhook
     * has landed, which is most of the time on a fast connection.
     *
     * @return array{status: string, amount: int, currency: string}|null
     */
    public function verify(string $reference): ?array
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->acceptJson()
            ->timeout(15)
            ->get($this->endpoint("/transaction/verify/{$reference}"));

        if ($response->failed() || $response->json('status') !== true) {
            return null;
        }

        return [
            'status' => (string) $response->json('data.status'),
            'amount' => (int) $response->json('data.amount'),
            'currency' => (string) $response->json('data.currency'),
        ];
    }

    /**
     * Whether a webhook body genuinely came from Paystack.
     *
     * HMAC-SHA512 of the **raw** body with the secret key, compared in
     * constant time. The raw body matters: re-encoding a decoded payload
     * changes key order and whitespace, and the digest with it.
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        if (blank($signature) || ! self::isConfigured()) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret'));

        return hash_equals($expected, $signature);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.paystack.base_url'), '/').$path;
    }

    /** Where Paystack sends the customer back to once they have paid. */
    private function callbackUrl(Order $order): string
    {
        $storefront = rtrim((string) config('services.paystack.callback_base_url'), '/');

        return "{$storefront}/order-confirmation/{$order->reference}";
    }
}
