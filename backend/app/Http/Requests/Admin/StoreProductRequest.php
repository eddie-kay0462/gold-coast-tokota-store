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
            'base_price_ghs' => ['required', 'integer', 'min:0'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            // out_of_stock/limited_stock are always computed (Product::getEffectiveBadgeAttribute)
            // — admin can only set the one editorial state that can't be inferred from stock.
            'merchandising_badge' => ['nullable', Rule::in(['back_in_stock'])],
        ];
    }
}
