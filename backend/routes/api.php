<?php

use App\Http\Controllers\Api\V1\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\V1\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\V1\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Api\V1\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Api\V1\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\WorkshopSessionController as AdminWorkshopSessionController;
use App\Http\Controllers\Api\V1\Admin\WorkshopTypeController as AdminWorkshopTypeController;
use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingUploadController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\FxRateController;
use App\Http\Controllers\Api\V1\NewsletterSubscriptionController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PaystackWebhookController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductStockController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\WorkshopSessionController;
use App\Http\Controllers\Api\V1\WorkshopTypeController;
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

    // Password reset. Both are unauthenticated by definition — someone who
    // could authenticate would not need them.
    //
    // Throttled hard, and per IP rather than per email: the endpoint sends
    // mail to an address the caller chooses, so an unthrottled one is a way to
    // use this API to spam a third party. The broker's own 60-second
    // per-email throttle sits underneath and stops repeat sends to a single
    // real account; this ceiling is what stops a script working through a
    // list of addresses.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword'])
            ->name('password.email');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
            ->name('password.reset');
    });

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

    // Paystack's webhook — where an order actually becomes paid. Its own
    // throttle, well above the API baseline: a burst of retries after an
    // outage is exactly when this must not be rate-limited into another
    // retry. Authenticity comes from the HMAC signature, not from the route
    // being obscure.
    Route::post('/webhooks/paystack', PaystackWebhookController::class)
        ->middleware('throttle:300,1')
        ->name('webhooks.paystack');

    // Booking system (Feature 7) — Workshop (capacity/waitlist) + DIY orders
    // (unlimited/queue-based). No auth required: guest bookings are supported,
    // same as guest checkout.
    // The six experiences §15 publishes. Separate from the session list
    // below: three of them run only by appointment and so never have a
    // bookable date, and without this endpoint half the programme is
    // invisible to customers.
    // A DIY reference photo, uploaded before the booking that will claim it.
    // Unauthenticated for the same reason the booking itself is — guests are
    // supported — and therefore throttled hard: this is the only endpoint on
    // the API that writes to disk without a session behind it. Uploads nothing
    // ever claims are swept by PruneOrphanedBookingUploads.
    Route::post('/booking-uploads', [BookingUploadController::class, 'store'])
        ->middleware('throttle:10,60')
        ->name('booking-uploads.store');

    Route::get('/workshop-types', [WorkshopTypeController::class, 'index'])->name('workshop-types.index');
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
            // No capability gate on either: an account whose access has
            // lapsed still has to be able to see who it is and sign out.
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');

            // Every route below names the capability it needs rather than a
            // tier, because the brand document's permission model (§18) does
            // not divide cleanly by tier — an Admin may issue refunds but not
            // touch payment credentials, a Staff member may adjust stock but
            // not price it. See App\Support\AdminCapability.

            // Inventory is Staff work: restocking is day-to-day operations,
            // not a pricing decision.
            Route::get('/inventory', [AdminInventoryController::class, 'index'])
                ->middleware('capability:inventory.view')->name('inventory.index');
            Route::patch('/inventory/{inventoryItem}', [AdminInventoryController::class, 'update'])
                ->middleware('capability:inventory.adjust')->name('inventory.update');

            Route::get('/feedback', [AdminFeedbackController::class, 'index'])
                ->middleware('capability:customers.view')->name('feedback.index');

            // Dashboard. Direct queries, no pre-aggregation — the README
            // requires metrics to reflect live data on every load. Revenue
            // figures inside are gated separately on analytics.revenue.
            Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics'])
                ->middleware('capability:analytics.view')->name('dashboard.metrics');
            Route::get('/dashboard/charts', [AdminDashboardController::class, 'charts'])
                ->middleware('capability:analytics.view')->name('dashboard.charts');

            // Orders. Viewing and moving an order through fulfilment is Staff
            // work; issuing a refund is not, and that is enforced on the
            // submitted status inside UpdateOrderStatusRequest.
            Route::get('/orders', [AdminOrderController::class, 'index'])
                ->middleware('capability:orders.view')->name('orders.index');
            Route::get('/orders/{reference}', [AdminOrderController::class, 'show'])
                ->middleware('capability:orders.view')->name('orders.show');
            Route::patch('/orders/{reference}', [AdminOrderController::class, 'updateStatus'])
                ->middleware('capability:orders.update_status')->name('orders.update-status');

            // Returns and exchanges (brand document §9/§21). Staff record and
            // track them; only an Admin resolves one, because a resolution is
            // a refund, a replacement or a credit.
            Route::get('/returns', [AdminReturnController::class, 'index'])
                ->middleware('capability:returns.view')->name('returns.index');
            Route::post('/returns', [AdminReturnController::class, 'store'])
                ->middleware('capability:returns.view')->name('returns.store');
            Route::get('/returns/{returnRequest}', [AdminReturnController::class, 'show'])
                ->middleware('capability:returns.view')->name('returns.show');
            Route::patch('/returns/{returnRequest}', [AdminReturnController::class, 'update'])
                ->middleware('capability:returns.resolve')->name('returns.update');

            // Bookings and workshop capacity (Feature 7 admin side).
            Route::get('/bookings', [AdminBookingController::class, 'index'])
                ->middleware('capability:bookings.view')->name('bookings.index');
            Route::patch('/bookings/{booking}', [AdminBookingController::class, 'updateStatus'])
                ->middleware('capability:bookings.update_status')->name('bookings.update-status');

            // The six experiences the brand document §15 defines. Read-only:
            // their days, times and capacities are the published programme,
            // not an operational setting — a session scheduled against one is
            // where the day-to-day changes happen.
            Route::get('/workshop-types', [AdminWorkshopTypeController::class, 'index'])
                ->middleware('capability:bookings.view')->name('workshop-types.index');

            Route::get('/workshop-sessions', [AdminWorkshopSessionController::class, 'index'])
                ->middleware('capability:bookings.view')->name('workshop-sessions.index');
            Route::post('/workshop-sessions', [AdminWorkshopSessionController::class, 'store'])
                ->middleware('capability:workshops.manage')->name('workshop-sessions.store');
            Route::put('/workshop-sessions/{workshopSession}', [AdminWorkshopSessionController::class, 'update'])
                ->middleware('capability:workshops.manage')->name('workshop-sessions.update');
            Route::delete('/workshop-sessions/{workshopSession}', [AdminWorkshopSessionController::class, 'destroy'])
                ->middleware('capability:workshops.manage')->name('workshop-sessions.destroy');

            // Native CMS (Feature 9). Rich-text bodies are sanitised
            // server-side on the way in — see HtmlSanitizer.
            Route::get('/blog', [AdminBlogPostController::class, 'index'])
                ->middleware('capability:content.view')->name('blog.index');
            Route::post('/blog', [AdminBlogPostController::class, 'store'])
                ->middleware('capability:content.write')->name('blog.store');
            Route::get('/blog/{blogPost}', [AdminBlogPostController::class, 'show'])
                ->middleware('capability:content.view')->name('blog.show');
            Route::put('/blog/{blogPost}', [AdminBlogPostController::class, 'update'])
                ->middleware('capability:content.write')->name('blog.update');
            Route::delete('/blog/{blogPost}', [AdminBlogPostController::class, 'destroy'])
                ->middleware('capability:content.delete')->name('blog.destroy');

            // Pages are edited, never created or deleted: their slugs are
            // storefront routes, so inventing one publishes a page nothing
            // links to and removing one breaks a live URL.
            Route::get('/pages', [AdminPageController::class, 'index'])
                ->middleware('capability:content.view')->name('pages.index');
            Route::get('/pages/{page}', [AdminPageController::class, 'show'])
                ->middleware('capability:content.view')->name('pages.show');
            Route::put('/pages/{page}', [AdminPageController::class, 'update'])
                ->middleware('capability:content.write')->name('pages.update');

            Route::get('/newsletter', [AdminNewsletterController::class, 'index'])
                ->middleware('capability:customers.view')->name('newsletter.index');
            Route::get('/newsletter/export', [AdminNewsletterController::class, 'export'])
                ->middleware('capability:customers.export')->name('newsletter.export');

            Route::get('/categories', [AdminCategoryController::class, 'index'])
                ->middleware('capability:products.view')->name('categories.index');

            // Customers are read-only: their details are theirs to change,
            // and deletion is a data-protection policy question rather than
            // a button.
            Route::get('/customers', [AdminCustomerController::class, 'index'])
                ->middleware('capability:customers.view')->name('customers.index');
            Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])
                ->middleware('capability:customers.view')->name('customers.show');

            // Shipments are a view over orders, not a second table — see
            // ShipmentController.
            Route::get('/shipments', [AdminShipmentController::class, 'index'])
                ->middleware('capability:shipments.view')->name('shipments.index');

            // Media library. This is what makes products.images fillable.
            Route::get('/media', [AdminMediaController::class, 'index'])
                ->middleware('capability:content.view')->name('media.index');
            Route::post('/media', [AdminMediaController::class, 'store'])
                ->middleware('capability:media.upload')->name('media.store');
            Route::delete('/media/{mediaAsset}', [AdminMediaController::class, 'destroy'])
                ->middleware('capability:media.delete')->name('media.destroy');

            // Site Settings is readable by Staff (the WhatsApp number shows on
            // screens they use) but writable only by Admin.
            Route::get('/site-settings', [AdminSiteSettingController::class, 'show'])
                ->middleware('capability:settings.view')->name('site-settings.show');
            Route::put('/site-settings', [AdminSiteSettingController::class, 'update'])
                ->middleware('capability:settings.write')->name('site-settings.update');

            // Read-only reflections of how the system is configured. No write
            // endpoints: changing a gateway key from a web form would let the
            // running config and the deployment's config disagree.
            Route::prefix('settings')->name('settings.')->group(function () {
                // Currency, FX cadence and the published policy constants.
                // Informational, so settings.view like the screen that reads
                // it — settings.fx gates *changing* FX configuration, which
                // no endpoint offers.
                Route::get('/commerce', [AdminSettingsController::class, 'commerce'])
                    ->middleware('capability:settings.view')->name('commerce');
                // Payment credentials, even masked, are Super Admin only:
                // §18 puts them outside the Admin tier explicitly.
                Route::get('/payments', [AdminSettingsController::class, 'payments'])
                    ->middleware('capability:settings.payments')->name('payments');

                Route::get('/delivery', [AdminSettingsController::class, 'delivery'])
                    ->middleware('capability:settings.view')->name('delivery');
                Route::get('/notifications', [AdminSettingsController::class, 'notifications'])
                    ->middleware('capability:settings.view')->name('notifications');
                Route::get('/whatsapp', [AdminSettingsController::class, 'whatsapp'])
                    ->middleware('capability:settings.view')->name('whatsapp');

                // Editorial content rather than config, so it lives on the
                // SiteSetting controller — routed here because that is where
                // the admin Workshops screen looks for it.
                Route::get('/diy-turnaround', [AdminSiteSettingController::class, 'diyTurnaround'])
                    ->middleware('capability:settings.view')->name('diy-turnaround');
                Route::put('/diy-turnaround', [AdminSiteSettingController::class, 'updateDiyTurnaround'])
                    ->middleware('capability:settings.write')->name('diy-turnaround.update');
            });

            // Who has access is the most privileged decision in the system,
            // and §18 reserves it: an Admin "cannot modify system-level
            // settings". Everyone may see who is on the team; only the Super
            // Admin may change it.
            Route::get('/team', [AdminTeamController::class, 'index'])
                ->middleware('capability:team.view')->name('team.index');
            Route::post('/team', [AdminTeamController::class, 'store'])
                ->middleware('capability:team.manage')->name('team.store');
            Route::put('/team/{adminUser}', [AdminTeamController::class, 'update'])
                ->middleware('capability:team.manage')->name('team.update');
            Route::delete('/team/{adminUser}', [AdminTeamController::class, 'destroy'])
                ->middleware('capability:team.manage')->name('team.destroy');

            // Products. Reads are unscoped — drafts and withdrawn products
            // are exactly what this screen exists to show — and sit on
            // products.view, which Staff and Intern both hold: seeing the
            // catalogue is not the same as repricing it.
            Route::get('/products', [AdminProductController::class, 'index'])
                ->middleware('capability:products.view')->name('products.index');
            Route::get('/products/{product}', [AdminProductController::class, 'show'])
                ->middleware('capability:products.view')->name('products.show');

            // A write touches price, so it needs both.
            Route::post('/products', [AdminProductController::class, 'store'])
                ->middleware('capability:products.write,pricing.write')->name('products.store');
            Route::put('/products/{product}', [AdminProductController::class, 'update'])
                ->middleware('capability:products.write,pricing.write')->name('products.update');
            Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])
                ->middleware('capability:products.delete')->name('products.destroy');
        });
    });
});
