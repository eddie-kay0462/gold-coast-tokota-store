<?php

namespace App\Services\Payment;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Services\GiftCards\GiftCardService;
use App\Services\Notifications\OpsAlert;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records what Paystack says about a payment, from either of the two places
 * it can come from:
 *
 *   - the webhook (PaystackWebhookController), Paystack telling us;
 *   - verification (`confirmByVerifying`), us asking Paystack, while the
 *     order confirmation page waits. The webhook can land minutes after the
 *     customer is back from Paystack, and never reaches a local machine.
 *
 * Both write the same `processed_webhook_events` key (`charge.success:<ref>`)
 * in the same transaction as the order change, so whichever arrives first
 * records the payment and the other is a no-op. A payment is never recorded
 * twice and `OrderPaid` never fires twice.
 */
class PaystackPayments
{
    /** How often one order may be checked with Paystack while its page polls. */
    private const VERIFY_EVERY_SECONDS = 8;

    public function __construct(
        private readonly PaystackService $paystack,
        private readonly GiftCardService $giftCards,
    ) {}

    /**
     * A successful charge. Returns the order it marked paid, or null when it
     * marked nothing (already recorded, unknown order, wrong amount).
     */
    public function recordSuccess(string $reference, int $amount, string $currency): ?Order
    {
        try {
            $paid = DB::transaction(function () use ($reference, $amount, $currency) {
                $this->markProcessed('charge.success', $reference);

                return $this->applyPayment($reference, $amount, $currency);
            });
        } catch (UniqueConstraintViolationException) {
            // Already handled, by the other path or an earlier delivery.
            // Caught outside the transaction, which has rolled back by now:
            // Postgres refuses every further statement in a transaction that
            // has hit a constraint violation.
            return null;
        }

        // After the commit, so a queued listener never reads an order that
        // is not yet paid in the database.
        if ($paid) {
            OrderPaid::dispatch($paid);
        }

        return $paid;
    }

    /** A failed charge. Returns false when this event was already recorded. */
    public function recordFailure(string $reference): bool
    {
        try {
            DB::transaction(function () use ($reference) {
                $this->markProcessed('charge.failed', $reference);

                Order::query()
                    ->where(fn ($q) => $q->where('reference', $reference)->orWhere('payment_reference', $reference))
                    ->where('status', 'pending')
                    // Not cancelled: the customer may retry with another card,
                    // and the reservation holds until it expires on its own.
                    // `ReleaseExpiredReservations` gives the stock back.
                    ->update(['payment_reference' => $reference]);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /** Records any other event once, so a replay is recognised. */
    public function recordOther(string $event, string $reference): bool
    {
        try {
            DB::transaction(fn () => $this->markProcessed($event, $reference));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * Asks Paystack whether a pending order has been paid, and records the
     * payment if so. At most once every few seconds per order, however often
     * the confirmation page polls. Returns the order, fresh.
     */
    public function confirmByVerifying(Order $order): Order
    {
        if ($order->status !== 'pending' || $order->payment_gateway !== 'paystack' || ! PaystackService::isConfigured()) {
            return $order;
        }

        if (! Cache::add("paystack:verify:{$order->id}", true, self::VERIFY_EVERY_SECONDS)) {
            return $order;
        }

        // The reference the current link was opened under, then the order's
        // own: a resent link pays under `GCT-…-2`, the first link under the
        // order reference, and the customer may have used either.
        $references = array_values(array_unique(array_filter([$order->payment_reference, $order->reference])));

        foreach ($references as $reference) {
            $result = $this->paystack->verify($reference);

            if ($result && $result['status'] === 'success') {
                $this->recordSuccess($reference, $result['amount'], $result['currency']);

                break;
            }
        }

        return $order->fresh(['items.product', 'shipment']);
    }

    private function markProcessed(string $event, string $reference): void
    {
        // Paystack sends no per-delivery id, so the event name plus the
        // transaction reference stands in: unique per state change, which is
        // the property idempotency needs.
        ProcessedWebhookEvent::create([
            'gateway' => 'paystack',
            'event_id' => "{$event}:{$reference}",
            'event_type' => $event,
            'processed_at' => now(),
        ]);
    }

    /** Runs inside recordSuccess's transaction. */
    private function applyPayment(string $reference, int $amount, string $currency): ?Order
    {
        $order = $this->orderFor($reference)?->load('items');

        if (! $order) {
            Log::warning('Paystack reported a payment for an unknown order.', ['reference' => $reference]);

            return null;
        }

        if ($order->status !== 'pending') {
            // Paid already, and now paid again through its other link (a
            // WhatsApp order whose link was resent). Both charges are real;
            // nothing here can undo one, so a person has to refund it.
            if ($order->payment_reference && $order->payment_reference !== $reference && $order->status === 'paid') {
                Log::error('Order paid twice through two payment links. Refund the second charge.', [
                    'order_reference' => $order->reference,
                    'recorded_payment' => $order->payment_reference,
                    'second_payment' => $reference,
                ]);
                app(OpsAlert::class)->paymentNeedsRefund($order, $reference, 'the order was already paid through its other payment link');
            }

            // A cancelled order (a WhatsApp order the team called off) paid
            // through a link the customer still had.
            if ($order->status === 'cancelled') {
                Log::error('Payment received for a cancelled order. Refund it, or reinstate the order.', [
                    'order_reference' => $order->reference,
                    'payment_reference' => $reference,
                ]);
                app(OpsAlert::class)->paymentNeedsRefund($order, $reference, 'the order had been cancelled');
            }

            return null;
        }

        // What was charged has to be what we asked for. A mismatch is either a
        // partial payment or a tampered-with amount; neither is a completed
        // sale.
        if ($amount !== $order->total || $currency !== $order->currency) {
            Log::error('Paystack payment does not match the order total.', [
                'reference' => $reference,
                'charged' => "{$amount} {$currency}",
                'expected' => "{$order->total} {$order->currency}",
            ]);

            return null;
        }

        // Paid after its hold lapsed, by which time any gift card balance it
        // used had gone back to the card: take it again. The sale stands
        // either way; a shortfall is logged for a person to settle.
        $this->giftCards->reclaim($order);

        $order->update([
            'status' => 'paid',
            'payment_gateway' => 'paystack',
            'payment_reference' => $reference,
        ]);

        return $order;
    }

    /** Its own reference, or the fresh one a resent payment link was opened under. */
    private function orderFor(string $reference): ?Order
    {
        return Order::query()->where('reference', $reference)->first()
            ?? Order::query()->where('payment_reference', $reference)->first();
    }
}
