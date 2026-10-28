<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gift card or store credit for the admin. The full code is shown: staff
 * read it back to customers who have lost the email.
 */
class AdminGiftCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'source' => $this->source,
            'status' => $this->status,
            'is_expired' => $this->isExpired(),
            'initial_balance' => ['amount' => $this->initial_balance, 'currency' => 'GHS'],
            'balance' => ['amount' => $this->balance, 'currency' => 'GHS'],
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'sender_name' => $this->sender_name,
            'message' => $this->message,
            'note' => $this->note,
            'purchase_order_reference' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder?->reference),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->issuedBy?->name),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => ['amount' => $t->amount, 'currency' => 'GHS'],
                'balance_after' => ['amount' => $t->balance_after, 'currency' => 'GHS'],
                'order_reference' => $t->order?->reference,
                'admin' => $t->admin?->name,
                'note' => $t->note,
                'created_at' => $t->created_at,
            ])),
        ];
    }
}
