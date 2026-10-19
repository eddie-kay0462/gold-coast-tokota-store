<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesCheckoutItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An order built in the WhatsApp inbox. The same body a web checkout posts,
 * validated the same way (see StoreCheckoutSessionRequest): lines and an
 * address, never prices. The email is required for the same reason it is at
 * checkout: Paystack will not open a payment without one, and it is where
 * the confirmation goes.
 */
class StoreWhatsAppOrderRequest extends FormRequest
{
    use ResolvesCheckoutItems;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->itemRules(),
            'currency' => ['required', Rule::in(['GHS', 'USD'])],
            'shipping_quote_id' => ['nullable', 'uuid'],
            'shipping_option_id' => ['nullable', 'string', 'max:64'],
            ...$this->destinationRules(),
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.email' => ['required', 'email', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:32'],
            'draft_reference' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.email.required' => 'An email address is needed: Paystack sends the receipt there, and so do we.',
            'shipping_address.country.required' => 'A destination country is needed to work out delivery.',
            'items.required' => 'Add at least one item.',
        ];
    }
}
