<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('shortcut'))) {
            $this->merge(['shortcut' => strtolower(ltrim(trim($this->input('shortcut')), '/')) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:60'],
            'shortcut' => [
                'nullable', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('quick_replies', 'shortcut')->ignore($this->route('quickReply')),
            ],
            'body' => ['required', 'string', 'max:4096'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['shortcut.regex' => 'A shortcut is letters, numbers and dashes, like “hours”.'];
    }
}
