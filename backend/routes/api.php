<?php

use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\WorkshopSessionController as AdminWorkshopSessionController;
use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\FxRateController;
use App\Http\Controllers\Api\V1\NewsletterSubscriptionController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductStockController;
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
    // Polled by the storefront while a product page is open (Feature 3). A
    // plain read — checkout's row-level locking is what actually prevents
    // overselling, so this being stale is a display concern, never a
    // correctness one.
    Route::get('/products/{slug}/stock', [ProductStockController::class, 'show'])->name('products.stock');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::get('/fx-rate', [FxRateController::class, 'show'])->name('fx-rate.show');

    // Customer accounts (README Feature 4 — optional throughout; guest
    // checkout never requires one). Sanctum SPA cookie sessions on the `web`
    // guard, the mirror of the admin block below.
    //
    // Routes are guarded with `auth:web`, NOT `auth:sanctum`: config/sanctum.php
    // lists both `web` and `admin` in its guard array, so `auth:sanctum` would
    // let an admin session through here — and `$request->user()` would then be
    // an AdminUser whose id could collide with a real customer's.
    Route::post('/register', [CustomerAuthController::class, 'register'])->name('register');
    Route::post('/login', [CustomerAuthController::class, 'login'])->name('login');

    Route::middleware('auth:web')->group(function () {
        Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [CustomerAuthController::class, 'me'])->name('me');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    });

    // Feedback (Feature 9). Unauthenticated — the form on /help asks for a name
    // and an email rather than a login — so it is throttled, and a session is
    // attached when there happens to be one.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    });

    // Checkout and orders (Feature 4). Both are unauthenticated: guest
    // checkout is supported by design, so neither can sit behind a login.
    //
    // Rate-limited for that reason. Checkout creation reserves stock, so an
    // unthrottled endpoint is a way to hold the whole catalogue hostage; and
    // orders are addressed by a random reference, so throttling is what turns
    // "not practically guessable" into "not worth trying".
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/checkout/session', [CheckoutController::class, 'store'])->name('checkout.session');
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/orders/{reference}', [OrderController::class, 'show'])->name('orders.show');
    });

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

            // Inventory is Admin *and* Staff: restocking is day-to-day
            // operations, not a pricing decision.
            Route::middleware('staff_or_admin')->group(function () {
                Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
                Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');

                // Dashboard. Direct queries, no pre-aggregation — the README
                // requires metrics to reflect live data on every load.
                Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics'])->name('dashboard.metrics');

                // Orders. Viewing and moving an order through fulfilment is
                // Staff work; issuing a refund is not, and that is enforced on
                // the submitted status inside UpdateOrderStatusRequest.
                Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
                Route::get('/orders/{reference}', [AdminOrderController::class, 'show'])->name('orders.show');
                Route::patch('/orders/{reference}', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');

                // Bookings and workshop capacity (Feature 7 admin side).
                Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
                Route::patch('/bookings/{booking}', [AdminBookingController::class, 'updateStatus'])->name('bookings.update-status');

                Route::get('/workshop-sessions', [AdminWorkshopSessionController::class, 'index'])->name('workshop-sessions.index');
                Route::post('/workshop-sessions', [AdminWorkshopSessionController::class, 'store'])->name('workshop-sessions.store');
                Route::put('/workshop-sessions/{workshopSession}', [AdminWorkshopSessionController::class, 'update'])->name('workshop-sessions.update');
                Route::delete('/workshop-sessions/{workshopSession}', [AdminWorkshopSessionController::class, 'destroy'])->name('workshop-sessions.destroy');
            });

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
