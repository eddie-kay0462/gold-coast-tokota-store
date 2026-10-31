<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ShippingZone;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Controller;
use App\Http\Resources\FxRateResource;
use App\Models\ShippingSetting;
use App\Models\SiteSetting;
use App\Services\Currency\FxRateService;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Notifications\FishAfricaSmsService;
use App\Services\Returns\ReturnPolicy;
use App\Services\Shipping\ShippingManager;
use App\Services\WhatsApp\CloudApiClient;
use App\Support\NotificationTriggers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * The settings panels that describe how the system is *configured*, as opposed
 * to SiteSetting, which is content the brand edits.
 *
 * These are read-only reflections of `.env` and application constants. They are
 * not stored anywhere and there is no write endpoint, because changing a
 * payment gateway's key or the FX refresh cadence from a web form would mean
 * the running configuration and the deployment's configuration could disagree —
 * and the one people would trust is the wrong one.
 *
 * **No secret is ever returned in full.** Publishable keys are masked to their
 * last four characters and secret keys are reported only as present or absent.
 * An admin panel that displays a live Stripe secret has turned a session
 * compromise into a payments compromise.
 */
class SettingsController extends Controller
{
    public function commerce(): JsonResponse
    {
        return response()->json(['data' => [
            'base_currency' => 'GHS',
            'foreign_currency' => 'USD',
            'fx_provider' => config('services.exchangerate_host.base_url') ? 'exchangerate.host' : null,
            'fx_provider_configured' => (bool) config('services.exchangerate_host.key'),
            'fx_refresh_minutes' => 60,
            'reservation_ttl_minutes' => 15,
            'low_stock_threshold_default' => 2,
            // §8/§21: "Orders are processed within 48 hours after payment
            // confirmation."
            'processing_hours' => 48,
            // §9: seven days from receiving the order. This panel said 30,
            // which is not a figure that appears anywhere in the brand
            // document — an admin reading it would have quoted a window four
            // times longer than the published one.
            'returns_window_days' => ReturnPolicy::WINDOW_DAYS,
            'refund_processing_business_days' => ReturnPolicy::REFUND_BUSINESS_DAYS,
        ]]);
    }

    public function payments(): JsonResponse
    {
        return response()->json(['data' => [
            // "Enabled" means a secret key is actually configured, not a flag
            // someone set — so this panel cannot claim payments work when they
            // do not. Both are false today; see FakeGateway.
            'paystack_enabled' => (bool) config('services.paystack.secret'),
            'paystack_settlement_currency' => 'GHS',
            'paystack_methods' => ['card', 'mobile_money', 'bank_transfer'],
            'paystack_public_key_masked' => $this->mask(config('services.paystack.public')),

            'stripe_enabled' => (bool) config('services.stripe.secret'),
            'stripe_settlement_currency' => 'USD',
            'stripe_publishable_key_masked' => $this->mask(config('services.stripe.public')),

            'webhooks_configured' => [
                'paystack' => (bool) config('services.paystack.webhook_secret'),
                'stripe' => (bool) config('services.stripe.webhook_secret'),
            ],
        ]]);
    }

    public function delivery(ShippingManager $shipping): JsonResponse
    {
        $zones = [];

        foreach (ShippingZone::cases() as $zone) {
            $configured = (array) config("shipping.zones.{$zone->value}", []);

            $zones[] = [
                'zone' => $zone->value,
                'label' => $zone->label(),
                // The chain as configured, and what actually serves the zone
                // now — a carrier that is switched off or not built yet is
                // skipped, and the zone falls back to manual dispatch.
                'configured' => $configured,
                'active' => array_map(fn ($carrier) => $carrier->name(), $shipping->chainFor($zone)),
            ];
        }

        return response()->json(['data' => [
            'zones' => $zones,
            // Straight from §8's shipping tables, which are published
            // customer-facing promises.
            'processing_hours' => DeliveryEstimates::PROCESSING_HOURS,
            'domestic_eta_label' => DeliveryEstimates::DOMESTIC,
            'international_bands' => DeliveryEstimates::bands(),
            'quote_buffer_percent' => (int) config('shipping.quote_buffer_percent'),
            // What an unpriced Ghana region gets: the Ghana fallback rate, or
            // refused and sent to WhatsApp.
            'unpriced_regions' => config('shipping.unpriced_regions') === 'refuse' ? 'refuse' : 'fallback',
            'fallback_rates' => [
                'accra' => ['amount' => ShippingSetting::fallbackRate('accra'), 'currency' => 'GHS'],
                'ghana' => ['amount' => ShippingSetting::fallbackRate('ghana'), 'currency' => 'GHS'],
                'international' => ['amount' => ShippingSetting::fallbackRate('international'), 'currency' => 'GHS'],
            ],
            'ops_alerts' => [
                'email' => (bool) config('shipping.ops.email'),
                'sms' => (bool) config('shipping.ops.phone'),
            ],
        ]]);
    }

    public function notifications(): JsonResponse
    {
        return response()->json(['data' => [
            'sms_provider' => 'fish_africa',
            'sms_enabled' => (bool) config('services.fish_africa.app_secret'),
            'email_from_name' => config('mail.from.name'),
            'email_from_address' => config('mail.from.address'),
            // The four triggers README Feature 8 names, plus the dispatch
            // notification the brand document promises under Domestic
            // Shipping. All five dispatch as of 8 Sep.
            // The four triggers README Feature 8 names, plus the dispatch
            // notification the brand document promises under Domestic
            // Shipping, each switchable per channel (31 Oct).
            'triggers' => NotificationTriggers::all(),
            'dispatch_implemented' => true,
            // Without Fish Africa credentials the SMS half falls back to
            // LogSmsChannel, which writes the message to the log instead of
            // sending it. The panel says so rather than implying texts are
            // going out — the same honesty FakeGateway gets on the payments
            // screen.
            'sms_channel' => FishAfricaSmsService::isConfigured() ? 'fish_africa' : 'log_only',
        ]]);
    }

    public function whatsapp(): JsonResponse
    {
        $settings = SiteSetting::current();
        $connected = CloudApiClient::isConfigured();

        return response()->json(['data' => [
            // True once the Cloud API credentials are set in the environment.
            // Until then the inbox runs on LogWhatsAppClient and every "sent"
            // message is marked simulated.
            'connected' => $connected,
            'integration' => $connected ? 'cloud_api' : 'wa_me_link',
            'phone_number_id' => $this->mask(config('whatsapp.phone_number_id')),
            'waba_id' => $this->mask(config('whatsapp.waba_id')),
            'webhook_url' => route('api.v1.webhooks.whatsapp.receive'),
            // Whether the two values Meta needs for the webhook are set, never
            // the values themselves.
            'webhook_signing_configured' => filled(config('whatsapp.app_secret')),
            'webhook_verify_token_configured' => filled(config('whatsapp.verify_token')),
            'last_webhook_at' => Cache::get(WhatsAppWebhookController::LAST_RECEIVED_KEY),
            'graph_version' => config('whatsapp.graph_version'),
            'display_number' => $settings->whatsapp_number,
            'default_message' => $settings->whatsapp_default_message,
        ]]);
    }

    /** Last four characters only — enough to tell two keys apart, not to use one. */
    /**
     * Fetch the exchange rate now instead of waiting for the hourly job.
     * A failed fetch keeps the last good rate (FxRateService) and says so.
     */
    public function refreshFx(FxRateService $fx): JsonResponse|FxRateResource
    {
        $rate = $fx->fetchLatest();

        if ($rate === null) {
            return response()->json([
                'message' => 'The rate provider didn’t answer, so the last rate is still in use. Try again in a few minutes.',
            ], 502);
        }

        return new FxRateResource($rate);
    }

    /** The per-channel switches; anything not sent stays as it was. */
    public function updateNotifications(Request $request): JsonResponse
    {
        $data = $request->validate([
            'triggers' => ['required', 'array'],
            'triggers.*.key' => ['required', 'string', Rule::in(array_keys(NotificationTriggers::TRIGGERS))],
            'triggers.*.email' => ['required', 'boolean'],
            'triggers.*.sms' => ['required', 'boolean'],
        ]);

        $settings = SiteSetting::current();
        $channels = $settings->notification_channels ?? [];
        foreach ($data['triggers'] as $trigger) {
            $channels[$trigger['key']] = ['email' => (bool) $trigger['email'], 'sms' => (bool) $trigger['sms']];
        }
        $settings->update(['notification_channels' => $channels]);

        return $this->notifications();
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
