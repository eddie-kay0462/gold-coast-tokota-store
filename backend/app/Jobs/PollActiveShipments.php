<?php

namespace App\Jobs;

use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every minute: queue a status refresh for each shipment with a carrier that
 * is due one. Each shipment carries its own next_poll_at, so a claim being
 * confirmed is checked every few seconds' worth of minutes and a parcel in
 * transit far less often, from one schedule entry.
 *
 * Manual shipments are never polled — a person records their progress.
 */
class PollActiveShipments implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config('shipping.queue'));
    }

    public function handle(): void
    {
        Shipment::query()
            ->active()
            ->where('carrier', '!=', 'manual')
            ->whereNotNull('carrier_reference')
            ->where(fn ($q) => $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now()))
            ->lazyById(100)
            ->each(fn (Shipment $shipment) => RefreshShipmentStatus::dispatch($shipment));
    }
}
