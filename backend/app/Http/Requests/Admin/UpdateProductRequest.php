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
            'base_price_ghs' => ['sometimes', 'required', 'integer', 'min:0'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
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
