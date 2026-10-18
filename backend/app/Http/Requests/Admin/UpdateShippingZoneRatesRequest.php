<?php

namespace App\Http\Requests\Admin;

use App\Models\ShippingZoneRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The regional flat rates, saved together — it is one table on one screen,
 * and a half-saved price list is worse than none.
 */
class UpdateShippingZoneRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.region' => ['required', 'string', 'distinct', Rule::in(ShippingZoneRate::REGIONS)],
            // GHS minor units.
            'rates.*.amount_ghs' => ['required', 'integer', 'min:0', 'max:10000000'],
            'rates.*.is_active' => ['required', 'boolean'],
        ];
    }
}
