<?php

namespace App\Jobs;

use App\Contracts\ConfirmsDispatch;
use App\Exceptions\YangoAcceptBlockedException;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShippingManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks a carrier where one shipment is, and acts on the answer.
 *
 *   - priced and waiting for us (Yango's ready_for_approval): confirm it;
 *   - no courier, or still searching past the carrier's limit: hand to
 *     HandleCourierNotFound;
 *   - anything else: record it — the order follows, and the customer hears
 *     once it is on its way.
 *
 * Queued by PollActiveShipments when a shipment's next_poll_at comes due.
 * It never re-queues itself; the schedule is the loop. This is also where a
 * carrier webhook would slot in: it would only queue this job, never trust
 * its own payload.
 */
class RefreshShipmentStatus implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** One refresh per shipment in flight at a time. */
    public int $uniqueFor = 120;

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

        if (! $shipment->status->isActive() || $shipment->carrier === 'manual' || $shipment->carrier_reference === null) {
            return;
        }

        $carrier = $shipping->forShipment($shipment);
        $result = $carrier->status($shipment);

        if ($result->noCourier || $shipments->searchTimedOut($shipment)) {
            $shipments->applyStatus($shipment, $result);
            HandleCourierNotFound::dispatch($shipment);

            return;
        }

        if ($result->needsConfirmation && $carrier instanceof ConfirmsDispatch) {
            $shipments->applyStatus($shipment, $result);

            try {
                $shipments->recordBooking($shipment, $carrier->confirm($shipment), 'Booking confirmed; looking for a courier.');
            } catch (YangoAcceptBlockedException $e) {
                // Staging with a real token: the claim exists and is priced,
                // and will lapse unaccepted. Nothing more to poll.
                $shipments->markFailed($shipment, $e->getMessage());
            }

            return;
        }

        $shipments->applyStatus($shipment, $result);
    }
}
