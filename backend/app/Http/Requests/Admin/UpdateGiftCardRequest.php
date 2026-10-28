<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Disabling or re-enabling a card, or correcting its balance with a reason. */
class UpdateGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'balance' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'note' => ['required_with:balance', 'nullable', 'string', 'max:500'],
        ];
    }
}
