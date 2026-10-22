<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp webhook: verification (GET) and deliveries (POST).
 *
 * The same three properties as the Paystack receiver, in the same order:
 *
 *   1. Authenticity. Anyone can POST here, and a forged delivery would put
 *      words in a customer's mouth. Meta signs the raw body with the app
 *      secret (X-Hub-Signature-256); anything unsigned or mis-signed is
 *      refused before it is parsed. With no app secret configured nothing
 *      can be verified, so everything is refused.
 *   2. Idempotency. Meta delivers at least once. The processor keys every
 *      message on its wamid and only ever moves a status forward, so a
 *      replay changes nothing.
 *   3. Speed. Meta retries what it does not get a quick 200 for, so the work
 *      is queued and the answer is immediate.
 */
class WhatsAppWebhookController extends Controller
{
    /** The cache key the admin's connection panel reads. */
    public const LAST_RECEIVED_KEY = 'whatsapp:last_webhook_at';

    /** Meta's one-off check that this URL belongs to us. */
    public function verify(Request $request): Response
    {
        $token = config('whatsapp.verify_token');

        // PHP turns the `hub.mode` query key into `hub_mode`.
        if (
            $request->query('hub_mode') === 'subscribe'
            && filled($token)
            && hash_equals((string) $token, (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Verification failed', 403);
    }

    public function receive(Request $request): Response
    {
        if (! $this->signatureIsValid($request->getContent(), $request->header('x-hub-signature-256'))) {
            Log::warning('Rejected a WhatsApp webhook with an invalid signature.', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('Ignored', 200);
        }

        Cache::forever(self::LAST_RECEIVED_KEY, now()->toIso8601String());

        if (config('whatsapp.log_payloads')) {
            // The raw body, so field names and nesting are exactly Meta's.
            Log::channel('whatsapp_payloads')->info('webhook', ['body' => $request->getContent()]);
        }
        ProcessWhatsAppWebhook::dispatch($payload);

        return response('OK', 200);
    }

    private function signatureIsValid(string $rawBody, ?string $header): bool
    {
        $secret = config('whatsapp.app_secret');

        if (blank($secret) || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, (string) $secret), $header);
    }
}
