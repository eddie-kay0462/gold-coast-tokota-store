<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Copies a photo or document a customer sent into the private bucket.
 *
 * WhatsApp's media links expire and need the access token, so the file is
 * fetched once, while it is still there, and kept in `r2-private`: these are
 * customers' photos (feet, measurements, receipts) and are only ever shown
 * through a short-lived signed link.
 */
class DownloadWhatsAppMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $messageId,
        public readonly string $mediaId,
    ) {}

    public function handle(WhatsAppClient $client): void
    {
        $message = WhatsAppMessage::query()->find($this->messageId);

        if (! $message || $message->media_path) {
            return;
        }

        $file = $client->downloadMedia($this->mediaId);

        if (! $file) {
            return;
        }

        $mime = $file['mime_type'];
        $extension = match (true) {
            str_contains($mime, 'jpeg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mp4') => 'mp4',
            default => pathinfo((string) $message->media_filename, PATHINFO_EXTENSION) ?: 'bin',
        };

        $path = sprintf('whatsapp/%d/%s.%s', $message->conversation_id, Str::ulid(), $extension);
        MediaStorage::privateDisk()->put($path, $file['contents']);

        $message->update(['media_path' => $path, 'media_mime' => $message->media_mime ?? $mime]);
    }
}
