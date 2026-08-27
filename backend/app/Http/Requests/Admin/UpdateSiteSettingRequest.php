<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Site Settings — **Admin only**, named explicitly in the README's two-tier
 * rule alongside pricing and refunds. The route carries the `admin` middleware;
 * this class validates shape.
 */
class UpdateSiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Digits only, no punctuation: this is interpolated straight into a
            // wa.me URL site-wide, and a number with spaces or a leading + in
            // it produces a link that silently goes nowhere.
            'whatsapp_number' => ['sometimes', 'string', 'regex:/^[0-9]{6,15}$/'],
            'whatsapp_default_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'instagram_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'hero_headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hero_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'diy_turnaround_estimate' => ['sometimes', 'nullable', 'string', 'max:120'],
            'announcements' => ['sometimes', 'array', 'max:5'],
            'announcements.*' => ['string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'Enter the number in full international form, digits only — e.g. 233200000000.',
        ];
    }
}
