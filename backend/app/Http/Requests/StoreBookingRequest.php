<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isWorkshop = $this->input('type') === 'workshop';
        $isDiy = $this->input('type') === 'diy_order';

        return [
            'type' => ['required', Rule::in(['workshop', 'diy_order'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'workshop_session_id' => [Rule::requiredIf($isWorkshop), 'nullable', 'integer', 'exists:workshop_sessions,id'],

            'details' => ['required', 'array'],
            'details.name' => ['required', 'string', 'max:255'],
            'details.email' => ['required', 'email'],
            'details.phone' => ['required', 'string', 'max:32'],

            // Workshop-only fields.
            'details.attendee_count' => [Rule::requiredIf($isWorkshop), 'nullable', 'integer', 'min:1'],

            // DIY-only fields.
            'details.size' => [Rule::requiredIf($isDiy), 'nullable', 'string', 'max:20'],
            'details.foot_length' => [Rule::requiredIf($isDiy), 'nullable', 'numeric'],
            'details.fulfilment' => [Rule::requiredIf($isDiy), 'nullable', Rule::in(['pickup', 'delivery'])],
            'details.reference_image' => ['nullable', 'string'],
        ];
    }
}
