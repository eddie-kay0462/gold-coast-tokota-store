<?php

namespace App\Http\Requests;

use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A storefront deployed before sign-up asked for two names still sends one
     * `name`. Split it here rather than refuse the sign-up, so the API can go
     * out before or after the storefront.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('first_name') && $this->filled('name')) {
            [$first, $last] = PersonName::split((string) $this->input('name'));
            $this->merge(['first_name' => $first, 'last_name' => $last]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            // Required for a new sign-up; only rows from before the split may
            // lack one. Not required on the legacy one-word `name` path.
            'last_name' => [Rule::requiredIf(! $this->filled('name')), 'nullable', 'string', 'max:120'],
            // Plainly unique, and deliberately not "claim any existing row
            // that has no password yet". Nothing creates passwordless Customer
            // rows today — guest checkout leaves customer_id null rather than
            // writing one — and if post-purchase account creation ever does,
            // letting registration adopt such a row would hand anyone who knows
            // a customer's email their full order history. Any future claiming
            // flow has to go through an emailed confirmation link, not this.
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:32'],
            'preferred_currency' => ['nullable', Rule::in(['GHS', 'USD'])],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Enter your first name.',
            'last_name.required' => 'Enter your last name.',
        ];
    }
}
