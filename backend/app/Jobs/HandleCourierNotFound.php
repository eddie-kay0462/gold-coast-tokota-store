<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\Shipping\ShipmentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * No courier took the job: cancel, retry, fall back, or tell a person. The
 * decision is ShipmentService::courierNotFound's; this only runs it off the
 * poller's back, once per shipment.
 */
class HandleCourierNotFound implements ShouldBeUnique, ShouldQueue
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

    public function handle(ShipmentService $shipments): void
    {
        $shipments->courierNotFound($this->shipment);
    }
}
