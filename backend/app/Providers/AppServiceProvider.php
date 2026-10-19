<?php

namespace App\Providers;

use App\Services\Notifications\NotificationChannelFactory;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Shipping\ShippingManager;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppClientFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Feature 8. Resolved through the factory so which SMS channel is in
        // play — Fish Africa or the log fallback — is decided in exactly one
        // place, the same way PaymentGatewayFactory decides between Paystack
        // and FakeGateway. Jobs type-hint the dispatcher and never the
        // channels, so a test can swap the whole thing out in one line.
        $this->app->singleton(
            NotificationDispatcher::class,
            fn () => (new NotificationChannelFactory)->make(),
        );

        // One manager per process, so a driver registered with extend() —
        // in a later phase, or by a test — is the one every caller gets.
        $this->app->singleton(ShippingManager::class);

        // The WhatsApp Cloud API, or the logging stand-in without credentials.
        // Decided once, here; everything else type-hints the interface, so a
        // test can swap in a fake with one line.
        $this->app->singleton(WhatsAppClient::class, fn () => (new WhatsAppClientFactory)->make());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The floor under every API route — see bootstrap/app.php. Generous
        // enough that the storefront's inventory polling (every 15-30s per open
        // product page) never trips it, tight enough to be a real ceiling.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        //
    }
}
