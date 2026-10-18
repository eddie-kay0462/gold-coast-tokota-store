<?php

namespace App\Jobs;

use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentBookingFailedException;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShippingManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Books the courier for a packed shipment.
 *
 * Unique per shipment, and the drivers are idempotent underneath (Yango
 * through request_id, and none re-books a shipment that already has a
 * carrier reference), so a retry or a double dispatch never sends two
 * couriers. Confirmation, where a carrier needs one, is the poller's job:
 * the price takes a few seconds to appear.
 */
class DispatchShipment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly Shipment $shipment)
    {
        $this->onQueue(config('shipping.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->shipment->id;
    }

    public function handle(ShippingManager $shipping, ShipmentService $shipments): void
    {
        $shipment = $this->shipment->fresh();

        if ($shipment->status !== ShipmentStatus::Booking || $shipment->carrier === 'manual') {
            return;
        }

        try {
            $result = $shipping->forShipment($shipment)->dispatch($shipment);
        } catch (ShipmentBookingFailedException $e) {
            // Not retried: see the exception. A person takes it from here.
            $shipments->markFailed($shipment, $e->getMessage());

            return;
        }

        $shipments->recordBooking($shipment, $result, 'Booking requested from '.ShipmentService::carrierName($shipment->carrier).'.');
    }

    /** Out of retries: a person has to book this one. */
    public function failed(Throwable $e): void
    {
        app(ShipmentService::class)->markFailed(
            $this->shipment->fresh(),
            'The courier could not be booked: '.$e->getMessage(),
        );
    }
}
