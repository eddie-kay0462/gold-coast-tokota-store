<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\Shipping\ShipmentService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Opens a shipment the moment money arrives, so every paid order has one and
 * nothing depends on someone remembering to create it.
 *
 * Opens, not books. README Feature 5 said to book the courier on payment;
 * this does not, deliberately. A Yango courier arrives within minutes of
 * booking, and an order is processed over up to 48 hours (§21), so booking
 * waits for staff to mark the parcel packed. See FOR_THE_TEAM.md.
 */
class CreateShipmentForPaidOrder implements ShouldQueue
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function handle(OrderPaid $event): void
    {
        $this->shipments->createForPaidOrder($event->order);
    }
}
