<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stands in when the Cloud API is not configured: development, tests, and a
 * production API before the number is connected. Every send "succeeds" with
 * a local id and is written to the log, so the inbox, the order flow and the
 * automation can all be exercised end to end, and the messages it records
 * carry `provider = log` so nobody mistakes them for delivered.
 */
class LogWhatsAppClient implements WhatsAppClient
{
    public function name(): string
    {
        return 'log';
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->log('text', $to, ['body' => $body]);
    }

    public function sendTemplate(string $to, string $name, string $language, array $components = []): SendResult
    {
        return $this->log('template', $to, ['name' => $name, 'language' => $language, 'components' => $components]);
    }

    public function sendCtaUrl(string $to, string $body, string $buttonText, string $url): SendResult
    {
        return $this->log('cta_url', $to, ['body' => $body, 'button' => $buttonText, 'url' => $url]);
    }

    public function downloadMedia(string $mediaId): ?array
    {
        return null;
    }

    public function fetchTemplates(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $payload */
    private function log(string $type, string $to, array $payload): SendResult
    {
        Log::info('WhatsApp message not sent (Cloud API not configured).', ['type' => $type, 'to' => $to, ...$payload]);

        return SendResult::sent('log.'.Str::ulid());
    }
}
