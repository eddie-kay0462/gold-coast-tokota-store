<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product)],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            // The shop's type filter (slippers, ahenema, closed-toe…).
            'product_type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'base_price_ghs' => ['sometimes', 'required', 'integer', 'min:0'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            // Reorder or remove only. New photos go through
            // POST /admin/products/{product}/photos, which names and stores
            // them the catalogue way (CataloguePhoto); a path typed in here
            // would skip that.
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'distinct', Rule::in($product->images ?? [])],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'merchandising_badge' => ['nullable', Rule::in(['back_in_stock'])],

            // Shipping and customs (international delivery).
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'width_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'height_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'hs_code' => ['nullable', 'string', 'regex:/^\d{4}(\.?\d{2}){0,3}$/'],
            'origin_country' => ['sometimes', 'string', 'size:2', 'alpha'],
            'customs_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
