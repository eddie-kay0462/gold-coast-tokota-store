<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageReceived;
use App\Services\WhatsApp\WhatsAppOrders;

/**
 * A customer who tapped "Order on WhatsApp" arrives with a code in their
 * message ("Order code: WA-7KQ2MP"). This links their chat to the cart they
 * carried over, so the team opens the order builder with it already filled.
 *
 * Not queued: it runs inside the already-queued webhook job.
 */
class LinkWhatsAppOrderDraft
{
    public function __construct(private readonly WhatsAppOrders $orders) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        $this->orders->linkDraftFromMessage($event->message);
    }
}
