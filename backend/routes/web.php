<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Paystack stand-in for local development — never registered in production,
// where it would complete payments that never happened. See
// FakeGatewayController.
if (! app()->isProduction()) {
    Route::get('/fake-gateway/{reference}', \App\Http\Controllers\Dev\FakeGatewayController::class)
        ->where('reference', 'fake_[a-z0-9]+')
        ->name('fake-gateway');
}
