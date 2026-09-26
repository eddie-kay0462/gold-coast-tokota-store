<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannel;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationRecipient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS through Fish Africa (README Feature 8), authenticated with an App
 * ID/App Secret bearer token against https://api.letsfish.africa.
 *
 * **Unverified against the live API.** No credentials exist yet, so the
 * request shape below follows the README's description of the provider and has
 * never been run against the real endpoint. When keys land, expect to correct
 * the token exchange and the send payload — that is why both are in one small
 * class with everything else behind the NotificationChannel interface. The
 * README also asks for a sandbox test before relying on this: Ghana network
 * delivery rates are explicitly unverified ("Clarifications Needed" #3).
 */
class FishAfricaSmsService implements NotificationChannel
{
    public function name(): string
    {
        return 'sms';
    }

    public static function isConfigured(): bool
    {
        return (bool) config('services.fish_africa.app_id')
            && (bool) config('services.fish_africa.app_secret');
    }

    public function send(NotificationRecipient $to, NotificationMessage $message): bool
    {
        // No phone number, or a message not worth a text — both ordinary, and
        // neither is a failure. Email still goes out either way.
        if (! $to->phone || ! $message->sms) {
            return false;
        }

        $phone = self::normalisePhone($to->phone);

        if ($phone === null) {
            // Feature 8's edge case names this exactly: a malformed number
            // fails gracefully and is logged rather than throwing, so the
            // email half of the same notification is unaffected.
            throw new RuntimeException("Unusable phone number for SMS: [{$to->phone}].");
        }

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(10)
            ->post(rtrim((string) config('services.fish_africa.base_url'), '/').'/sms/send', [
                'to' => $phone,
                'message' => $message->sms,
                'sender' => config('mail.from.name'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Fish Africa rejected the send ({$response->status()}): {$response->body()}"
            );
        }

        return true;
    }

    private function token(): string
    {
        return base64_encode(
            config('services.fish_africa.app_id').':'.config('services.fish_africa.app_secret')
        );
    }

    /**
     * To E.164, which is what an SMS gateway wants.
     *
     * Ghanaian numbers are written locally as `024 123 4567` — ten digits
     * beginning with a zero — and that leading zero has to become +233 or the
     * message goes nowhere. A number already in international form is left
     * alone, so an international customer's number survives untouched.
     */
    public static function normalisePhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if (str_starts_with($digits, '+')) {
            return strlen($digits) >= 8 ? $digits : null;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        // Local Ghanaian form: 0XXXXXXXXX -> +233XXXXXXXXX.
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+233'.substr($digits, 1);
        }

        // Already country-coded but without the plus.
        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        return null;
    }
}
