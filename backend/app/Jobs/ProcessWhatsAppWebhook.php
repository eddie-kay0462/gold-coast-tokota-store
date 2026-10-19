<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued so the webhook can answer Meta at once. Meta retries any delivery
 * it does not get a quick 200 for, and the processor is idempotent, so a
 * retry here is always safe.
 */
class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    /** @param  array<string, mixed>  $payload */
    public function __construct(public readonly array $payload) {}

    public function handle(WhatsAppWebhookProcessor $processor): void
    {
        $processor->process($this->payload);
    }
}
