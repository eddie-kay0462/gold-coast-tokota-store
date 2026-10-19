<?php

namespace App\Http\Resources\Admin;

use App\Models\WhatsAppConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One inbox conversation, in the shape admin/types/messaging.ts `ChatThread`
 * declares (the admin app camelises keys on arrival).
 *
 * @mixin WhatsAppConversation
 */
class ChatThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contact = $this->contact;

        return [
            'id' => (string) $this->id,
            'contact_name' => $contact?->displayName(),
            'contact_phone' => $contact?->phone_e164,
            'avatar' => null,
            'topic' => $this->topic,
            'status' => $this->status,
            'unread_count' => $this->unread_count,
            // The Cloud API does not report presence, so these stay off rather
            // than pretending.
            'is_online' => false,
            'last_seen_at' => null,
            'last_message_preview' => $this->last_message_preview ?? '',
            'last_message_at' => $this->last_message_at ?? $this->created_at,
            // Null when the customer has never written (a thread started from
            // the phone, or imported): only a template can open it.
            'session_expires_at' => $this->serviceWindowClosesAt(),
            'linked_order_id' => $this->linked_order_id,
            'linked_order_reference' => $this->whenLoaded('linkedOrder', fn () => $this->linkedOrder?->reference),
            'linked_booking_id' => $this->linked_booking_id,
            'assigned_to_admin_id' => $this->assigned_admin_id,
            'assigned_to_name' => $this->whenLoaded('assignedAdmin', fn () => $this->assignedAdmin?->name),
            'is_archived' => $this->status === 'closed',
            'customer_id' => $contact?->customer_id,
            'opted_out' => (bool) $contact?->opted_out_at,
        ];
    }
}
