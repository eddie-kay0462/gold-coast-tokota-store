<?php

namespace App\Http\Requests\Admin;

use App\Models\GiftCard;
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
            'code_word' => ['nullable', 'string', GiftCard::CODE_WORD_RULE],
            'send_email' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code_word.regex' => 'A code word is 2 to 10 letters or numbers, with no spaces.',
        ];
    }
}
