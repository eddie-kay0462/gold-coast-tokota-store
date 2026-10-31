<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Product::create() saves an in-memory model with only the attributes it
     * was given — it never re-reads the row back, so any column left out
     * here would report as null in the response even though the DB applied
     * its own default. Setting explicit defaults keeps the two in sync.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured', false),
            'images' => $this->input('images', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            // The shop's type filter (slippers, ahenema, closed-toe…).
            'product_type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'base_price_ghs' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            // Photos are added once the product exists, through
            // POST /admin/products/{product}/photos (CataloguePhoto names
            // them from the slug), never as paths here.
            'images' => ['nullable', 'array', 'max:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            // out_of_stock/limited_stock are always computed (Product::getEffectiveBadgeAttribute)
            // — admin can only set the one editorial state that can't be inferred from stock.
            'merchandising_badge' => ['nullable', Rule::in(['back_in_stock'])],

            // Shipping and customs (international delivery).
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'length_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'width_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'height_mm' => ['nullable', 'integer', 'min:1', 'max:3000'],
            'hs_code' => ['nullable', 'string', 'regex:/^\d{4}(\.?\d{2}){0,3}$/'],
            'origin_country' => ['sometimes', 'string', 'size:2', 'alpha'],
            'customs_description' => ['nullable', 'string', 'max:255'],
            // Optional: given, the stock grid is made from them (ProductVariants).
            ...UpdateProductVariantsRequest::variantRules(false),
        ];
    }
}
