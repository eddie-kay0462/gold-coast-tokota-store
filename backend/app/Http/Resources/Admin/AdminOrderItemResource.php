<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'inventory_item_id' => $this->inventory_item_id,
            // Snapshots — what was actually bought, not what the product says now.
            'name' => $this->product_name,
            'variant_label' => $this->variant_label,
            'quantity' => $this->quantity,
            'unit_price' => ['amount' => $this->unit_price, 'currency' => $this->currency],
            'line_total' => ['amount' => $this->unit_price * $this->quantity, 'currency' => $this->currency],
        ];
    }
}
