<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment has been confirmed for an order (README Feature 4).
 *
 * Dispatched from the webhook, never from the checkout endpoint: an open
 * payment session is a customer looking at a payment page, not a sale. Every
 * consequence of money actually arriving — finalising the stock hold, the
 * confirmation email, the SMS — hangs off this and nothing else.
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
