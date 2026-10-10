<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Services\Payment\PaystackService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
    public function __invoke(Request $request, PaystackService $paystack): Response
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

        // Paystack sends no per-delivery id, so the event name plus the
        // transaction reference stands in: unique per state change, which is
        // the property idempotency actually needs.
        //
        // The marker and the order change commit together. Written first and
        // on its own, a failure in between left the event marked processed
        // with the order still pending — and Paystack's retry, which is the
        // whole recovery mechanism, was then answered "already processed".
        try {
            $paid = DB::transaction(function () use ($request, $event, $reference) {
                ProcessedWebhookEvent::create([
                    'gateway' => 'paystack',
                    'event_id' => "{$event}:{$reference}",
                    'event_type' => $event,
                    'processed_at' => now(),
                ]);

                return match ($event) {
                    'charge.success' => $this->recordPayment($request, $reference),
                    'charge.failed' => $this->recordFailure($reference),
                    default => null,
                };
            });
        } catch (UniqueConstraintViolationException) {
            // Already handled. Acknowledge so Paystack stops retrying. Caught
            // outside the transaction, which has rolled back by now — Postgres
            // refuses every further statement in a transaction that has hit a
            // constraint violation.
            return response('Already processed', 200);
        }

        // After the commit, so a queued listener never reads an order that
        // is not yet paid in the database.
        if ($paid) {
            OrderPaid::dispatch($paid);
        }

        return response('OK', 200);
    }

    /** Returns the order it marked paid, or null when it marked nothing. */
    private function recordPayment(Request $request, string $reference): ?Order
    {
        $order = Order::query()->with('items')->where('reference', $reference)->first();

        if (! $order) {
            Log::warning('Paystack reported a payment for an unknown order.', ['reference' => $reference]);

            return null;
        }

        // Already paid — a duplicate that slipped past the event key because
        // Paystack sent it under a different event name.
        if ($order->status !== 'pending') {
            return null;
        }

        // What was charged has to be what we asked for. A mismatch is either a
        // partial payment or a tampered-with amount; neither is a completed
        // sale, and quietly accepting the smaller figure would be the
        // expensive way to find that out.
        $amount = (int) $request->input('data.amount');
        $currency = (string) $request->input('data.currency');

        if ($amount !== $order->total || $currency !== $order->currency) {
            Log::error('Paystack payment does not match the order total.', [
                'reference' => $reference,
                'charged' => "{$amount} {$currency}",
                'expected' => "{$order->total} {$order->currency}",
            ]);

            return null;
        }

        $order->update([
            'status' => 'paid',
            'payment_gateway' => 'paystack',
            'payment_reference' => $reference,
        ]);

        return $order;
    }

    private function recordFailure(string $reference): void
    {
        Order::query()
            ->where('reference', $reference)
            ->where('status', 'pending')
            // Not cancelled: the customer may retry with another card, and the
            // reservation holds until it expires on its own. `ReleaseExpired
            // Reservations` is what eventually gives the stock back.
            ->update(['payment_reference' => $reference]);
    }
}
