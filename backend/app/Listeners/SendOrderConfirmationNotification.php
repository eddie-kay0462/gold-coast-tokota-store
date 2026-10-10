<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Jobs\SendOrderNotification;

/**
 * Hangs the order confirmation off `OrderPaid` — the event dispatched from the
 * webhook when money actually arrives, never from checkout when a payment
 * session is merely opened. A confirmation sent for an abandoned checkout
 * would be worse than none at all.
 *
 * Thin on purpose: it queues the job rather than doing the work, so the
 * retry and backoff policy lives in one place with the sending.
 */
class SendOrderConfirmationNotification
{
    public function handle(OrderPaid $event): void
    {
        SendOrderNotification::dispatch($event->order, 'paid');
    }
}
