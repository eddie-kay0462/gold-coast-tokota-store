<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A product's sizes and colourways, which decide its stock rows. Also the
 * optional part of creating a product (StoreProductRequest reuses these).
 */
class UpdateProductVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function variantRules(bool $required): array
    {
        return [
            'sizes' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:30'],
            'sizes.*' => ['required', 'string', 'max:10', 'distinct:ignore_case'],
            'colours' => [$required ? 'present' : 'sometimes', 'array', 'max:12'],
            'colours.*.name' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'colours.*.hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function rules(): array
    {
        return [
            ...self::variantRules(true),
            'remove_stocked' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sizes.required' => 'Add at least one size.',
            'sizes.min' => 'Add at least one size.',
            'sizes.*.distinct' => 'Each size can only be listed once.',
            'colours.*.distinct' => 'Each colour can only be listed once.',
            'colours.*.hex.regex' => 'Colours are a # and six hex digits, e.g. #8B5A2B.',
        ];
    }
}
