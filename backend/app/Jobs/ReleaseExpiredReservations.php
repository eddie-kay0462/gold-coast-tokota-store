<?php

namespace App\Jobs;

use App\Services\Bookings\BookingCheckout;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InventoryReservationService $reservations, GiftCardService $giftCards, BookingCheckout $bookings): void
    {
        $reservations->releaseAllExpired();
        // Gift card balance held by the same lapsed checkouts goes back too.
        $giftCards->releaseLapsed();
        // And seats held by paid event bookings nobody paid for.
        $bookings->releaseLapsed();
    }
}
