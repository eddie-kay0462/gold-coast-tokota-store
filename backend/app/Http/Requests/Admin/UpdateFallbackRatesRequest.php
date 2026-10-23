<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The fallback delivery rates, in GHS minor units (pesewas). */
class UpdateFallbackRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Up to ₵10,000: a cap against a typo adding zeros, far above any
        // delivery rate.
        return [
            'accra_ghs' => ['required', 'integer', 'min:0', 'max:1000000'],
            'ghana_ghs' => ['required', 'integer', 'min:0', 'max:1000000'],
            'international_ghs' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Enter a rate for each zone.',
            '*.min' => 'A rate can’t be negative.',
            '*.max' => 'That rate is over ₵10,000. Check for an extra zero.',
        ];
    }
}
