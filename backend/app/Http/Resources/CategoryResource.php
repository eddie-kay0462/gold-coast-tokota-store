<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Admin only: the category list counts its products.
            'product_count' => $this->whenCounted('products'),
            // Admin only: the type most of its products carry, so the product
            // form can fill Type in from the category. Null for an empty one.
            'product_type' => $this->when(
                array_key_exists('usual_product_type', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('usual_product_type'),
            ),
        ];
    }
}
