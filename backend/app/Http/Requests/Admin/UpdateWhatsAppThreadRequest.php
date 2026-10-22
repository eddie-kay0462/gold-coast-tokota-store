<?php

namespace App\Http\Requests\Admin;

use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Assign, archive or reopen, change the topic, or link an order. */
class UpdateWhatsAppThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'topic' => ['sometimes', Rule::in(WhatsAppConversation::TOPICS)],
            'assigned_admin_id' => ['sometimes', 'nullable', 'integer', 'exists:admin_users,id'],
            // By reference, which is what a person has in front of them.
            'linked_order_reference' => ['sometimes', 'nullable', 'string', 'exists:orders,reference'],
        ];
    }
}
