<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Format only — deliberately **not** `exists:customers,email`. A
            // validation error on an unknown address turns this endpoint into
            // a way to ask "does this person have an account here", which is
            // exactly the enumeration the generic response in the controller
            // exists to prevent.
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
