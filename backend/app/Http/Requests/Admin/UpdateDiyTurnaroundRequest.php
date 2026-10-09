<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DIY turnaround tiers — **Admin only**. The README lists the DIY turnaround
 * estimate under Site Settings, which is Admin-tier alongside pricing and
 * refunds; the route carries the `admin` middleware.
 */
class UpdateDiyTurnaroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tiers' => ['required', 'array', 'max:12'],
            // Stable slug, so the storefront can look a tier up by order type
            // rather than by position in the list.
            'tiers.*.id' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', 'distinct'],
            'tiers.*.label' => ['required', 'string', 'max:120'],
            // Free text on purpose: "1-2 business days" and "1-3 weeks
            // (depending on quantity)" are both legitimate answers, and forcing
            // a number would make the second one unsayable.
            'tiers.*.estimate' => ['required', 'string', 'max:120'],
            'tiers.*.sort_order' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'tiers.*.id.regex' => 'A tier id can only contain lowercase letters, numbers and hyphens.',
            'tiers.*.id.distinct' => 'Two tiers cannot share the same id.',
        ];
    }
}
