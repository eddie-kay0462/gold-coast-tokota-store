<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Converts the soft reservation taken at checkout into a real decrement.
 *
 * Until payment lands, stock is only *held* — `quantity_reserved` is up and
 * `quantity_available` is untouched, so an abandoned checkout releases on its
 * own when `ReleaseExpiredReservations` next runs. This is where a held unit
 * actually leaves the shelf.
 *
 * Queued, and deliberately not inside the webhook's own transaction: the
 * webhook's job is to acknowledge Paystack quickly and record the payment
 * exactly once. Idempotency is guaranteed upstream by
 * `processed_webhook_events`, so this runs once per payment.
 */
class FinalizeInventoryForPaidOrder implements ShouldQueue
{
    public function __construct(private readonly InventoryReservationService $reservations) {}

    public function handle(OrderPaid $event): void
    {
        foreach ($event->order->items as $item) {
            if (! $item->inventoryItem) {
                // The variant was deleted between checkout and payment. The
                // sale stands — the money arrived — so this is a stock
                // correction for a human, not a reason to fail the order.
                Log::warning('Paid order item has no inventory item to finalise.', [
                    'order_reference' => $event->order->reference,
                    'order_item_id' => $item->id,
                ]);

                continue;
            }

            $this->reservations->finalize($item->inventoryItem, $item->quantity);
        }
    }
}
