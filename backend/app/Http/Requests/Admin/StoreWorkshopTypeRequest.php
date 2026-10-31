<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An experience on the booking page: one of §15's six, or a new one the client
 * adds (a one-off event, a new kind of workshop). The schedule fields are
 * labels shown to customers, as on the seeded six; actual dates and seats are
 * set per session.
 */
class StoreWorkshopTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'days_label' => [$required, 'string', 'max:120'],
            'slot_label' => [$required, 'string', 'max:120'],
            'duration_label' => [$required, 'string', 'max:60'],
            'capacity' => [$required, 'integer', 'min:1', 'max:100'],
            // Pesewas per person; null (or absent) is free.
            'price_ghs' => ['sometimes', 'nullable', 'integer', 'min:100', 'max:100000000'],
            // Whether a full session takes waitlist bookings or is sold out.
            'allow_waitlist' => ['sometimes', 'boolean'],
            'requires_appointment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
