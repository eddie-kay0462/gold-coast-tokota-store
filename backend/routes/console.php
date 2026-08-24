<?php

use App\Jobs\ReleaseExpiredReservations;
use App\Jobs\RefreshFxRate;
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
