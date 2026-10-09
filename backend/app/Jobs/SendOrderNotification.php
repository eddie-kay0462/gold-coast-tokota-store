<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\NotificationRecipient;
use App\Notifications\TransactionalMessages;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * README Feature 8's `SendOrderConfirmation`, widened to cover the dispatch
 * notification the brand document also promises — the two differ only in which
 * message they build, and a second near-identical job would be duplication.
 *
 * Queued so neither the Paystack webhook nor an admin status update waits on
 * an SMTP round trip. The webhook in particular has to answer Paystack
 * quickly, and a slow mail server is not a reason to make it retry.
 */
class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    /** Retried on transient failure, as Feature 8's acceptance criteria ask. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly Order $order,
        public readonly string $reason,
    ) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $message = match ($this->reason) {
            'shipped' => TransactionalMessages::orderShipped($this->order),
            default => TransactionalMessages::orderConfirmation($this->order->load('items')),
        };

        $dispatcher->send(NotificationRecipient::forOrder($this->order), $message);
    }
}
