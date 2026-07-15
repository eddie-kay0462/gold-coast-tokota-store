<?php

use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public, unauthenticated — power the SSR About page (Feature 1) and the
    // site-wide WhatsAppButton/footer (Feature 6). Full CRUD/admin editing
    // for these resources lands with the native CMS in Feature 9.
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::get('/site-settings', [SiteSettingController::class, 'show'])->name('site-settings.show');
});
