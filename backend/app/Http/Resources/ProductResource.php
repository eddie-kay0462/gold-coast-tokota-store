<?php

namespace App\Http\Resources;

use App\Services\Currency\FxRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // USD is always derived at read-time from base_price_ghs × the latest
        // cached FxRate — never stored as a static column (README Feature 2).
        $fxRate = app(FxRateService::class)->getCachedRate();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'base_price_ghs' => $this->base_price_ghs,
            'price_usd' => $fxRate ? (int) round($this->base_price_ghs * (float) $fxRate->rate) : null,
            'sku' => $this->sku,
            'images' => $this->images,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'in_stock' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->in_stock),
            'merchandising_badge' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->effective_badge),
            // The storefront strikes through sizes it cannot sell, so it needs
            // the range and the per-size sellable count — not just `in_stock`.
            'sizes' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->sizes),
            'size_availability' => $this->when($this->relationLoaded('inventoryItems'), fn () => (object) $this->size_availability),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
