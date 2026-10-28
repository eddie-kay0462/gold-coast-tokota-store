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
        //
        // The storefront renders on Vercel, so every server-side fetch arrives
        // from Vercel's address and one shared 120/min used to cover the whole
        // site: past it, product pages 404'd. When the request carries the
        // storefront's shared key, the visitor's own address (which the
        // storefront forwards) is the bucket instead, under a higher ceiling
        // for the storefront as a whole. Without the key, a forwarded address
        // is ignored, so it cannot be used to dodge the limit.
        RateLimiter::for('api', function (Request $request) {
            $visitor = self::storefrontVisitorIp($request);

            $perCaller = Limit::perMinute(120)
                ->by($request->user()?->id ?: ($visitor ?? $request->ip()));

            return $visitor === null
                ? $perCaller
                : [$perCaller, Limit::perMinute(3000)->by('storefront-server')];
        });
    }

    /**
     * The visitor address the storefront server forwarded, or null when the
     * request does not carry a valid `X-Storefront-Key`.
     */
    private static function storefrontVisitorIp(Request $request): ?string
    {
        $key = (string) config('services.storefront.key');
        $sent = (string) $request->header('X-Storefront-Key');

        if ($key === '' || $sent === '' || ! hash_equals($key, $sent)) {
            return null;
        }

        $ip = filter_var((string) $request->header('X-Storefront-Client-IP'), FILTER_VALIDATE_IP);

        return $ip === false ? null : $ip;
    }
}
