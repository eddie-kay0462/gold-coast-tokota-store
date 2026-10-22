<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One line of a variant's stock history (admin Inventory → History). */
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'reason' => $this->reason,
            'delta_available' => $this->delta_available,
            'delta_reserved' => $this->delta_reserved,
            'quantity_available_after' => $this->quantity_available_after,
            'quantity_reserved_after' => $this->quantity_reserved_after,
            'channel' => $this->channel,
            'order_reference' => $this->whenLoaded('order', fn () => $this->order?->reference),
            'admin_name' => $this->whenLoaded('adminUser', fn () => $this->adminUser?->name),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
