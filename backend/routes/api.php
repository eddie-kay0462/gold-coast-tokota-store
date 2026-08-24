<?php

use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\FxRateController;
use App\Http\Controllers\Api\V1\NewsletterSubscriptionController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\WorkshopSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public, unauthenticated — power the SSR About page (Feature 1) and the
    // site-wide WhatsAppButton/footer (Feature 6). Full CRUD/admin editing
    // for these resources lands with the native CMS in Feature 9.
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::get('/site-settings', [SiteSettingController::class, 'show'])->name('site-settings.show');

    // Public catalogue + live FX rate (Feature 2). Admin-only write endpoints
    // for products live under the /admin prefix below.
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::get('/fx-rate', [FxRateController::class, 'show'])->name('fx-rate.show');

    // Booking system (Feature 7) — Workshop (capacity/waitlist) + DIY orders
    // (unlimited/queue-based). No auth required: guest bookings are supported,
    // same as guest checkout.
    Route::get('/workshop-sessions', [WorkshopSessionController::class, 'index'])->name('workshop-sessions.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

    // Blog (Feature 9 CMS) + Newsletter (Feature 1 content flow). Admin
    // write endpoints for blog posts aren't built yet — no admin UI consumes
    // them yet either — see Feature 9.
    Route::get('/blog-posts', [BlogPostController::class, 'index'])->name('blog-posts.index');
    Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show'])->name('blog-posts.show');
    Route::post('/newsletter', [NewsletterSubscriptionController::class, 'store'])->name('newsletter.store');

    // Admin/Staff auth (Sanctum SPA cookie session against the 'admin' guard).
    // Login is unauthenticated by definition; its own per-email+IP lockout
    // (AdminLoginRequest) is the rate-limiting control (Feature 12).
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login');

        Route::middleware('auth:admin')->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');

            // Products: pricing/catalogue changes are Admin-only, not Staff
            // (README two-tier role rule — Staff has no pricing access).
            Route::middleware('admin')->group(function () {
                Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
                Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
                Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
            });
        });
    });
});
