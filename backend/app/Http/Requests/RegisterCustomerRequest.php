<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
}
