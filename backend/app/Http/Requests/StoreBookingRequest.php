<?php

namespace App\Http\Requests;

use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The storefront asks for first and last name, as checkout does; `name`
     * is made from them, so everything that reads a booking's name still
     * works. A request that sends only `name` (an older storefront) is still
     * accepted.
     */
    protected function prepareForValidation(): void
    {
        $details = $this->input('details');
        if (is_array($details) && isset($details['first_name']) && is_string($details['first_name'])) {
            $details['name'] = PersonName::join($details['first_name'], $details['last_name'] ?? null);
            $this->merge(['details' => $details]);
        }
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
            'details.first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'details.last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'details.name' => ['required', 'string', 'max:255'],
            'details.email' => ['required', 'email'],
            'details.phone' => ['required', 'string', 'max:32'],

            // Workshop-only fields.
            'details.attendee_count' => [Rule::requiredIf($isWorkshop), 'nullable', 'integer', 'min:1', 'max:100'],
            // What a paid session is charged in; the price is set in cedis.
            'currency' => ['sometimes', Rule::in(['GHS', 'USD'])],

            // DIY-only fields.
            'details.size' => [Rule::requiredIf($isDiy), 'nullable', 'string', 'max:20'],
            'details.foot_length' => [Rule::requiredIf($isDiy), 'nullable', 'numeric'],
            'details.fulfilment' => [Rule::requiredIf($isDiy), 'nullable', Rule::in(['pickup', 'delivery'])],
            'details.reference_image' => ['nullable', 'string'],
        ];
    }
}
