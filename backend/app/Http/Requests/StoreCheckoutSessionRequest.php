<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCheckoutItems;
use App\Support\PersonName;
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
    use ResolvesCheckoutItems {
        prepareForValidation as normaliseDestination;
    }

    /**
     * The recipient can come as `first_name` + `last_name` (the storefront's
     * form since 23 Oct) or as one `full_name` (the WhatsApp order builder, an
     * older storefront). With the parts, `full_name` is built from them, so
     * couriers, receipts and the admin, which all read `full_name`, are
     * unchanged; the parts are kept on the order's address too.
     */
    protected function prepareForValidation(): void
    {
        $this->normaliseDestination();

        $address = $this->input('shipping_address');
        if (is_array($address) && filled($address['first_name'] ?? null)) {
            $address['full_name'] = PersonName::join($address['first_name'], $address['last_name'] ?? null);
            $this->merge(['shipping_address' => $address]);
        }
    }

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
            'shipping_address.first_name' => ['nullable', 'string', 'max:120'],
            'shipping_address.last_name' => ['nullable', 'string', 'max:120'],
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
