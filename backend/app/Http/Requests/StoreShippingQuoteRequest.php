<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCheckoutItems;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Prices delivery for a cart before payment: `{ items, shipping_address }`.
 *
 * The same lines and address rules as the checkout session, minus contact
 * details and currency — a quote is always in cedis, and the storefront
 * converts for display the same way it does product prices.
 */
class StoreShippingQuoteRequest extends FormRequest
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
            ...$this->destinationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address.country.required' => 'A destination country is needed to work out delivery.',
            'items.required' => 'There is nothing to deliver.',
        ];
    }
}
