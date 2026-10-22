<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp Cloud API, over the Graph API.
 *
 * Sends never throw. A failure comes back as a SendResult, so the queued job
 * deciding whether to retry has one thing to look at, and a WhatsApp outage
 * can never take an admin request down with it.
 */
class CloudApiClient implements WhatsAppClient
{
    /**
     * Error codes worth retrying: throttling and Meta-side failures. Anything
     * else (an invalid number, a closed service window) fails the same way
     * every time.
     */
    private const RETRYABLE_CODES = [1, 2, 4, 80007, 130429, 131000, 131016, 131048, 131056, 133004];

    public function __construct(
        private readonly string $phoneNumberId,
        private readonly ?string $wabaId,
        private readonly string $accessToken,
        private readonly string $graphUrl = 'https://graph.facebook.com',
        private readonly string $graphVersion = 'v23.0',
        private readonly int $timeout = 15,
    ) {}

    public static function isConfigured(): bool
    {
        return filled(config('whatsapp.phone_number_id')) && filled(config('whatsapp.access_token'));
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('whatsapp.phone_number_id'),
            config('whatsapp.waba_id'),
            (string) config('whatsapp.access_token'),
            (string) config('whatsapp.graph_url'),
            (string) config('whatsapp.graph_version'),
            (int) config('whatsapp.timeout', 15),
        );
    }

    public function name(): string
    {
        return 'cloud_api';
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => false],
        ]);
    }

    public function sendTemplate(string $to, string $name, string $language, array $components = []): SendResult
    {
        return $this->send($to, [
            'type' => 'template',
            'template' => array_filter([
                'name' => $name,
                'language' => ['code' => $language],
                'components' => $components ?: null,
            ]),
        ]);
    }

    public function sendCtaUrl(string $to, string $body, string $buttonText, string $url): SendResult
    {
        return $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'cta_url',
                'body' => ['text' => $body],
                'action' => [
                    'name' => 'cta_url',
                    'parameters' => ['display_text' => $buttonText, 'url' => $url],
                ],
            ],
        ]);
    }

    public function downloadMedia(string $mediaId): ?array
    {
        try {
            $meta = $this->http()->get($this->endpoint($mediaId));

            if (! $meta->successful() || ! $meta->json('url')) {
                Log::warning('WhatsApp media lookup failed.', ['media_id' => $mediaId, 'status' => $meta->status()]);

                return null;
            }

            // The media URL is short-lived and needs the same bearer token.
            $file = $this->http()->get($meta->json('url'));

            if (! $file->successful()) {
                Log::warning('WhatsApp media download failed.', ['media_id' => $mediaId, 'status' => $file->status()]);

                return null;
            }

            return [
                'contents' => $file->body(),
                'mime_type' => (string) ($meta->json('mime_type') ?? $file->header('Content-Type') ?: 'application/octet-stream'),
            ];
        } catch (ConnectionException $e) {
            Log::warning('WhatsApp media download could not connect.', ['media_id' => $mediaId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function fetchTemplates(): array
    {
        if (blank($this->wabaId)) {
            return [];
        }

        $templates = [];
        $url = $this->endpoint("{$this->wabaId}/message_templates");
        $query = ['limit' => 100, 'fields' => 'id,name,status,category,language,components,rejected_reason'];

        // Paged by cursor; a WABA has tens of templates, so a few pages at most.
        for ($page = 0; $url && $page < 10; $page++) {
            $response = $this->http()->get($url, $query);

            if (! $response->successful()) {
                Log::warning('Fetching WhatsApp templates failed.', ['status' => $response->status(), 'error' => $response->json('error.message')]);

                break;
            }

            array_push($templates, ...($response->json('data') ?? []));
            $url = $response->json('paging.next');
            $query = [];
        }

        return $templates;
    }

    /** @param  array<string, mixed>  $payload */
    private function send(string $to, array $payload): SendResult
    {
        try {
            $response = $this->http()->post($this->endpoint("{$this->phoneNumberId}/messages"), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                ...$payload,
            ]);
        } catch (ConnectionException $e) {
            return SendResult::failed('Could not reach WhatsApp: '.$e->getMessage(), retryable: true);
        }

        if ($response->successful() && $response->json('messages.0.id')) {
            return SendResult::sent((string) $response->json('messages.0.id'));
        }

        return $this->failure($response);
    }

    private function failure(Response $response): SendResult
    {
        $code = $response->json('error.code');
        $message = (string) ($response->json('error.error_data.details')
            ?? $response->json('error.message')
            ?? "WhatsApp answered {$response->status()}");

        $retryable = $response->serverError()
            || $response->status() === 429
            || in_array($code, self::RETRYABLE_CODES, true);

        return SendResult::failed($message, is_int($code) ? $code : null, $retryable);
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->accessToken)->acceptJson()->timeout($this->timeout);
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->graphUrl, '/')."/{$this->graphVersion}/{$path}";
    }
}
