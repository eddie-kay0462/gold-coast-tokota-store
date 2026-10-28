<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The client's gift card policy. Nothing here has a default beyond "off" and
 * "never expires": the amounts and terms are theirs to write (§22).
 */
class UpdateGiftCardSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            // GHS minor units. Can't be switched on with nothing to sell.
            'amounts' => ['present', 'array', 'max:8', 'required_if:enabled,true'],
            'amounts.*' => ['integer', 'distinct', 'min:100', 'max:10000000'],
            'expiry_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amounts.required_if' => 'Add at least one amount before putting gift cards on sale.',
        ];
    }
}
