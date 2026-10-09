<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // An absolute count, not a delta: the person restocking has just
            // counted the shelf, and "there are 12" is what they know.
            'quantity_available' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
