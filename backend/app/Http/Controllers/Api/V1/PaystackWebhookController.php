<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaystackPayments;
use App\Services\Payment\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Paystack's webhook receiver — where an order actually becomes paid.
 *
 * Three properties this endpoint has to have, in order:
 *
 *   1. **Authenticity.** Anyone can POST here. The only thing separating a
 *      real payment notification from someone marking their own order paid is
 *      the HMAC signature, so an unsigned or mis-signed body is refused before
 *      it is even parsed as a payment.
 *   2. **Idempotency.** Paystack retries anything it does not get a 200 for.
 *      A replayed `charge.success` that ran twice would finalise the same
 *      reservation twice — one sale, two decrements. The unique index on
 *      `processed_webhook_events` is what makes the second one a no-op.
 *   3. **Speed.** Everything downstream of "the money arrived" is queued off
 *      OrderPaid rather than done inline, so a slow mailer cannot cause a
 *      timeout that triggers a retry of a payment already recorded.
 *
 * It always answers 200 to a signed request, including for events it does not
 * act on. A 4xx would put Paystack into a retry loop over a message that is
 * never going to be interesting.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackService $paystack, PaystackPayments $payments): Response
    {
        // The raw body, not a re-encode of the parsed array: re-encoding
        // changes key order and whitespace, and the digest with it.
        if (! $paystack->verifySignature($request->getContent(), $request->header('x-paystack-signature'))) {
            Log::warning('Rejected a Paystack webhook with an invalid signature.', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 401);
        }

        $event = (string) $request->input('event');
        $reference = (string) $request->input('data.reference');

        if ($reference === '') {
            return response('Ignored', 200);
        }

        // Recording is shared with verification (PaystackPayments), so a
        // payment the confirmation page already confirmed by asking Paystack
        // is recognised here as already processed, and the other way round.
        // A replay is answered 200 either way, so Paystack stops retrying.
        match ($event) {
            'charge.success' => $payments->recordSuccess(
                $reference,
                (int) $request->input('data.amount'),
                (string) $request->input('data.currency'),
            ),
            'charge.failed' => $payments->recordFailure($reference),
            default => $payments->recordOther($event, $reference),
        };

        return response('OK', 200);
    }
}
