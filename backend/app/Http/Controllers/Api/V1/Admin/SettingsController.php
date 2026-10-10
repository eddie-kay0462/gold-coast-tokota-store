<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Delivery\DeliveryEstimates;
use App\Services\Notifications\FishAfricaSmsService;
use App\Services\Returns\ReturnPolicy;
use Illuminate\Http\JsonResponse;

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

    public function delivery(): JsonResponse
    {
        return response()->json(['data' => [
            // Fixed by the README's routing rule, not configurable: Ghana goes
            // to Yango and everywhere else to DHL, and the acceptance criterion
            // is that the two never cross.
            'domestic_provider' => 'yango',
            'international_provider' => 'dhl',
            // Straight from §8's shipping tables, which are published
            // customer-facing promises. Every band here was previously a
            // guess, and two of them understated the real figure.
            'processing_hours' => 48,
            'domestic_eta_label' => DeliveryEstimates::DOMESTIC,
            'international_bands' => DeliveryEstimates::bands(),
            // Rates are a static table until credentials exist — the panel says
            // so rather than implying these came from a courier.
            'rates_are_live' => false,
            'providers_configured' => [
                'yango' => (bool) config('services.yango.key'),
                'dhl' => (bool) config('services.dhl.key'),
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
            'triggers' => [
                ['key' => 'order_placed', 'label' => 'Order placed', 'email' => true, 'sms' => true],
                ['key' => 'order_shipped', 'label' => 'Order dispatched', 'email' => true, 'sms' => true],
                ['key' => 'booking_submitted', 'label' => 'Booking submitted', 'email' => true, 'sms' => true],
                ['key' => 'booking_confirmed', 'label' => 'Booking confirmed', 'email' => true, 'sms' => true],
                ['key' => 'waitlist_promoted', 'label' => 'Waitlist promotion', 'email' => true, 'sms' => true],
            ],
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

        return response()->json(['data' => [
            // The admin screen is built for the WhatsApp Cloud API. The README
            // specifies wa.me deep links (Feature 6) and nothing else, so there
            // is no Cloud API connection to report — `connected: false` is the
            // truthful answer, not a placeholder.
            'connected' => false,
            'integration' => 'wa_me_link',
            'phone_number_id' => null,
            'waba_id' => null,
            'webhook_url' => null,
            'display_number' => $settings->whatsapp_number,
            'default_message' => $settings->whatsapp_default_message,
        ]]);
    }

    /** Last four characters only — enough to tell two keys apart, not to use one. */
    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
