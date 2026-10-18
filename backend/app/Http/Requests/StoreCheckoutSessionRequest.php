<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCheckoutItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The request body `CheckoutPaymentStep` is written against:
 * `{ items, currency, shipping_address, shipping_quote_id, shipping_option_id }`.
 *
 * Note what is absent: prices. The client sends an inventory item and a
 * quantity, and the server decides what that costs. A checkout that accepts a
 * posted price is a checkout that can be told what to charge — delivery
 * included: the client names a quote it was shown, by id, and the server
 * charges what it cached under that id.
 *
 * Both quote fields are optional. Without them the server prices delivery
 * itself and takes the zone's first option, which is what a client that
 * never showed a delivery price gets.
 */
class StoreCheckoutSessionRequest extends FormRequest
{
    use ResolvesCheckoutItems;

    public function authorize(): bool
    {
        // Guest checkout is supported by design (README Feature 4) — no forced
        // account creation. A logged-in customer is attached in the controller.
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
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.country.required' => 'A destination country is needed to work out delivery.',
            'items.required' => 'There is nothing in this order.',
        ];
    }
}
