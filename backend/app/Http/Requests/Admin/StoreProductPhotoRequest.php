<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $colours = array_column($product->colors ?? [], 'name');

        return [
            'file' => [
                'required',
                'file',
                // Phone photos run large; they're re-encoded to a ~100 KB WebP.
                'max:15360',
                // Contents, not extension or Content-Type (see StoreMediaAssetRequest).
                'mimes:jpg,jpeg,png,webp,avif',
                // Forces a real decode, and small enough to fill the
                // 1500×1667 card without obvious upscaling.
                'dimensions:min_width=600,min_height=600,max_width=8000,max_height=8000',
            ],
            // A product with colourways needs to know which one this is, or
            // the storefront's colour switch can't find it.
            'colour' => $colours
                ? ['required', 'string', Rule::in($colours)]
                : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a JPG, PNG, WebP or AVIF photo.',
            'file.max' => 'Photos must be 15MB or smaller.',
            'file.dimensions' => 'Photos must be at least 600 pixels on each side.',
            'colour.required' => 'Choose which colourway this photo shows.',
            'colour.in' => 'That colour isn’t one of this product’s colourways.',
        ];
    }
}
