<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What staff record after booking a courier themselves. The courier's name is
 * the one thing always known; a tracking number and link only exist when the
 * courier gives one.
 */
class ManualDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courier_name' => ['required', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:128'],
            'tracking_url' => ['nullable', 'url:https,http', 'max:2048'],
            // What the business paid the courier, GHS minor units.
            'cost_paid_ghs' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
