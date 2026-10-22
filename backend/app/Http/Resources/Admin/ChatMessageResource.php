<?php

namespace App\Http\Resources\Admin;

use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One message, in the shape admin/types/messaging.ts `ChatMessage` declares.
 *
 * @mixin WhatsAppMessage
 */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'thread_id' => (string) $this->conversation_id,
            'direction' => $this->direction,
            'kind' => $this->kind,
            'body' => (string) $this->body,
            // A route, not a file URL: the signed link is minted when someone
            // opens it, so a copied thread never leaks a customer's photo.
            'attachment' => $this->media_path ? [
                'filename' => $this->media_filename ?: basename($this->media_path),
                'url' => route('api.v1.admin.inbox.media', ['message' => $this->id]),
                'mime_type' => $this->media_mime ?? 'application/octet-stream',
            ] : ($this->media_mime ? [
                // Sent by the customer, still being fetched from WhatsApp.
                'filename' => $this->media_filename ?: 'Attachment',
                'url' => null,
                'mime_type' => $this->media_mime,
            ] : null),
            'status' => $this->status,
            'error' => $this->error,
            'source' => $this->source,
            'template_name' => $this->template_name,
            'cta' => $this->meta['cta'] ?? null,
            'sent_by_name' => $this->whenLoaded('sentBy', fn () => $this->sentBy?->name),
            'drafted_by_name' => $this->meta['drafted_by']['name'] ?? null,
            // The logging stand-in "sent" it: nothing reached WhatsApp.
            'simulated' => $this->provider === 'log',
            'sent_at' => $this->sent_at,
        ];
    }
}
