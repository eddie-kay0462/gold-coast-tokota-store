<?php

use App\Jobs\PollActiveShipments;
use App\Jobs\PruneOrphanedBookingUploads;
use App\Jobs\RefreshFxRate;
use App\Jobs\ReleaseExpiredReservations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh cadence per README Clarifications Needed #2 — hourly is a
// reasonable default until a final cadence is confirmed with the provider.
Schedule::job(new RefreshFxRate)->hourly();

// Reservation TTL is ~15 minutes (Feature 3) — every 5 minutes keeps
// wrongly-held stock from sitting unreleased for too much of that window.
Schedule::job(new ReleaseExpiredReservations)->everyFiveMinutes();

// `POST /booking-uploads` is unauthenticated by necessity (guest bookings), so
// files can accumulate from abandoned forms. Daily is ample: the job only
// removes uploads older than its own 24-hour grace window, so running it more
// often would delete nothing extra.
Schedule::job(new PruneOrphanedBookingUploads)->dailyAt('03:30');

// Courier bookings and parcels in flight. Every minute, but each shipment has
// its own next_poll_at, so this only refreshes the ones that are due: a Yango
// claim waiting to be confirmed (its offer lapses in 10 minutes) far more
// often than a parcel already on its way.
Schedule::job(new PollActiveShipments)->everyMinute()->withoutOverlapping();
