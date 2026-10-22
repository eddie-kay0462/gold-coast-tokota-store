<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A customer's message has been stored (never a replay, never an echo or a
 * history import). What reacts to a customer writing in, such as the
 * greeting, the after-hours reply and matching an order reference, listens
 * here.
 */
class WhatsAppMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WhatsAppMessage $message) {}
}
