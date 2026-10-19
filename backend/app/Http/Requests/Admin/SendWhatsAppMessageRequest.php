<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A reply from the inbox: free text, or an approved template with its values. */
class SendWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // WhatsApp's own limit for a text message is 4,096 characters.
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:4096'],
            'template_id' => ['required_without:body', 'nullable', 'integer', 'exists:whatsapp_templates,id'],
            'parameters' => ['nullable', 'array', 'max:10'],
            'parameters.*' => ['string', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Write a message, or pick a template.',
        ];
    }
}
