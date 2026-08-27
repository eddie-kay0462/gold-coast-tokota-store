<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
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
            'processing_hours' => 48,
            'returns_window_days' => 30,
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
            'domestic_eta_label' => '2-4 working days',
            'international_bands' => [
                ['region' => 'West Africa', 'eta' => '5-9 working days'],
                ['region' => 'Europe & UK', 'eta' => '7-14 working days'],
                ['region' => 'North America', 'eta' => '7-14 working days'],
                ['region' => 'Rest of world', 'eta' => '10-21 working days'],
            ],
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
            // The triggers README Feature 8 names. `email`/`sms` describe what
            // is *intended*; nothing dispatches yet — Feature 8 is unbuilt.
            'triggers' => [
                ['key' => 'order_placed', 'label' => 'Order placed', 'email' => true, 'sms' => true],
                ['key' => 'booking_submitted', 'label' => 'Booking submitted', 'email' => true, 'sms' => true],
                ['key' => 'booking_confirmed', 'label' => 'Booking confirmed', 'email' => true, 'sms' => true],
                ['key' => 'waitlist_promoted', 'label' => 'Waitlist promotion', 'email' => true, 'sms' => true],
            ],
            'dispatch_implemented' => false,
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
