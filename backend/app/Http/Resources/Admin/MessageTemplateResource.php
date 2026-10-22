<?php

namespace App\Http\Resources\Admin;

use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A WhatsApp template, in the shape admin/types/messaging.ts
 * `MessageTemplate` declares.
 *
 * @mixin WhatsAppTemplate
 */
class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => strtolower($this->category),
            'language' => $this->language,
            'status' => strtolower($this->status),
            'body' => $this->bodyText(),
            'variables' => $this->variables(),
            'rejection_reason' => $this->rejection_reason,
            'updated_at' => $this->synced_at ?? $this->updated_at,
        ];
    }
}
