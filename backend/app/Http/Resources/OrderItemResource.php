<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            // Snapshots, never the live product: a receipt has to keep saying
            // what was bought even after the product is renamed or deleted.
            'name' => $this->product_name,
            'variant_label' => $this->variant_label,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            // The two fields the confirmation page uses for links and
            // thumbnails, which are presentation rather than record — so they
            // do come from the live product, and go null when it is gone.
            'slug' => $this->whenLoaded('product', fn () => $this->product?->slug),
            'image' => $this->whenLoaded('product', fn () => $this->product?->images[0] ?? null),
        ];
    }
}
