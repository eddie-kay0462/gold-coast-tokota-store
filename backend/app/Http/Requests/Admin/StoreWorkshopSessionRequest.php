<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkshopSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'scheduled_date' => [$creating ? 'required' : 'sometimes', 'date', 'after_or_equal:today'],
            'scheduled_slot' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'capacity' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'location_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
