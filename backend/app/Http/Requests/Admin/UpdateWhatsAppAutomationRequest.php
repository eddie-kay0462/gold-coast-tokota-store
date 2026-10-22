<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_greeting' => ['sometimes', 'boolean'],
            'after_hours_enabled' => ['sometimes', 'boolean'],
            'after_hours_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hours' => ['sometimes', 'array'],
            'hours.days' => ['required_with:hours', 'array', 'min:1'],
            'hours.days.*' => ['integer', 'between:1,7', 'distinct'],
            'hours.open' => ['required_with:hours', 'date_format:H:i'],
            'hours.close' => ['required_with:hours', 'date_format:H:i', 'after:hours.open'],
        ];
    }

    public function messages(): array
    {
        return ['hours.close.after' => 'Closing time must be after opening time.'];
    }
}
