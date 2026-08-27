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
        // Stale rates are dropped rather than shown. The storefront already
        // falls back to cedis when price_usd is null, so a provider outage
        // degrades to "priced in GHS" instead of quoting a dollar figure that
        // checkout would then refuse to honour — the worse of the two
        // failures, because the customer only finds out at the payment step.
        $fxRate = app(FxRateService::class)->getUsableRate();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'base_price_ghs' => $this->base_price_ghs,
            'compare_at_ghs' => $this->compare_at_ghs,
            'price_usd' => $fxRate ? (int) round($this->base_price_ghs * (float) $fxRate->rate) : null,
            // Derived from compare_at_ghs on the same rate, so a sale price and
            // its was-price are never converted against different rates.
            'compare_at_usd' => $fxRate && $this->compare_at_ghs
                ? (int) round($this->compare_at_ghs * (float) $fxRate->rate)
                : null,
            'sku' => $this->sku,
            'images' => $this->images,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_pre_order' => $this->is_pre_order,

            // Listing facets. The storefront filters on these client-side today
            // (frontend/pages/shop/index.vue), so they must be present on every
            // product in a listing response, not just on detail.
            'product_type' => $this->product_type,
            'departments' => $this->departments,
            'widths' => $this->widths,
            'tags' => $this->tags,
            'color' => $this->color,
            'colors' => $this->colors,
            'in_stock' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->in_stock),
            'merchandising_badge' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->effective_badge),
            // The storefront strikes through sizes it cannot sell, so it needs
            // the range and the per-size sellable count — not just `in_stock`.
            'sizes' => $this->when($this->relationLoaded('inventoryItems'), fn () => $this->sizes),
            'size_availability' => $this->when($this->relationLoaded('inventoryItems'), fn () => (object) $this->size_availability),

            // Detail-page copy. Emitted on listings too — they are small, and a
            // separate listing resource would be one more place for the
            // contract to drift out of sync with the frontend's ApiProduct.
            'description_heading' => $this->description_heading,
            'model_note' => $this->model_note,
            'cost_breakdown' => $this->cost_breakdown,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
