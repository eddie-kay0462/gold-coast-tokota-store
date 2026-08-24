<?php

namespace App\Jobs;

use App\Services\Inventory\InventoryReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InventoryReservationService $reservations): void
    {
        $reservations->releaseAllExpired();
    }
}
