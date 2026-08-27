<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'currency' => $this->currency,
            // Null for GHS orders by definition — there is nothing to convert.
            'fx_rate_applied' => $this->fx_rate_applied ? (float) $this->fx_rate_applied : null,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'tax' => $this->tax,
            'total' => $this->total,
            'payment_gateway' => $this->payment_gateway,
            'delivery_provider' => $this->delivery_provider,
            'delivery_reference' => $this->delivery_reference,
            'shipping_address' => (object) $this->shipping_address,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
