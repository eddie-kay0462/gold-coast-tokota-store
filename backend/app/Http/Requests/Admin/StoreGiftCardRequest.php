<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Issuing store credit by hand (§11/§21: an item unavailable after purchase). */
class StoreGiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // GHS minor units: ₵1 to ₵100,000.
            'amount' => ['required', 'integer', 'min:100', 'max:10000000'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_email' => ['nullable', 'required_if:send_email,true', 'email', 'max:255'],
            // Why it was issued, for whoever reads the ledger later.
            'note' => ['required', 'string', 'max:500'],
            'send_email' => ['boolean'],
        ];
    }
}
