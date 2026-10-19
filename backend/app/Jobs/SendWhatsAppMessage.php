<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\SendResult;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one queued message. Retried on throttling and outages, like
 * SendOrderNotification; a failure WhatsApp will give again every time (an
 * invalid number, a closed window) is recorded at once instead.
 *
 * Only a `queued` message is sent, so a retry after a success, or a second
 * dispatch, can never send the same words twice.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $messageId) {}

    public function handle(WhatsAppClient $client): void
    {
        $message = WhatsAppMessage::query()->with('conversation.contact')->find($this->messageId);

        if (! $message || $message->status !== 'queued') {
            return;
        }

        $to = $message->conversation->contact->wa_id;
        $meta = $message->meta ?? [];

        $result = match ($message->kind) {
            'template' => $client->sendTemplate($to, $meta['template']['name'], $meta['template']['language'], $meta['template']['components'] ?? []),
            'interactive' => $client->sendCtaUrl($to, (string) $message->body, $meta['cta']['button'], $meta['cta']['url']),
            default => $client->sendText($to, (string) $message->body),
        };

        if ($result->ok) {
            $message->update(['wamid' => $result->wamid, 'status' => 'sent', 'provider' => $client->name(), 'error' => null]);

            return;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        $this->markFailed($message, $result);
    }

    public function failed(Throwable $e): void
    {
        $message = WhatsAppMessage::query()->find($this->messageId);

        if ($message && $message->status === 'queued') {
            $message->update(['status' => 'failed', 'error' => mb_strimwidth($e->getMessage(), 0, 250, '…')]);
        }
    }

    private function markFailed(WhatsAppMessage $message, SendResult $result): void
    {
        $message->update([
            'status' => 'failed',
            'error' => mb_strimwidth((string) $result->error, 0, 250, '…'),
            'meta' => [...($message->meta ?? []), 'error_code' => $result->errorCode],
        ]);
    }
}
