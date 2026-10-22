<?php

namespace App\Services\WhatsApp;

/**
 * Speaks to WhatsApp on the brand's behalf. Two implementations:
 * CloudApiClient (Meta's Graph API) and LogWhatsAppClient (writes to the log
 * and sends nothing), chosen by WhatsAppClientFactory from config.
 *
 * `$to` is always a wa_id: the customer's number in international format,
 * digits only, as WhatsApp reports it.
 */
interface WhatsAppClient
{
    /** `cloud_api` or `log`; stored on each message so the record says which ran. */
    public function name(): string;

    public function sendText(string $to, string $body): SendResult;

    /**
     * An approved template, the only thing WhatsApp accepts outside the
     * 24-hour service window.
     *
     * @param  array<int, array<string, mixed>>  $components  Cloud API `components`
     */
    public function sendTemplate(string $to, string $name, string $language, array $components = []): SendResult;

    /** A message with one link button (used for "Pay GHS 900"). */
    public function sendCtaUrl(string $to, string $body, string $buttonText, string $url): SendResult;

    /**
     * Downloads an inbound media item by its WhatsApp media id.
     *
     * @return array{contents: string, mime_type: string}|null
     */
    public function downloadMedia(string $mediaId): ?array;

    /**
     * The templates registered on the WhatsApp Business Account.
     *
     * @return list<array<string, mixed>> Cloud API template objects
     */
    public function fetchTemplates(): array;
}
