<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The request body `CheckoutPaymentStep` is written against:
 * `{ items, currency, shipping_address, delivery_method }`.
 *
 * Note what is absent: prices. The client sends an inventory item and a
 * quantity, and the server decides what that costs. A checkout that accepts a
 * posted price is a checkout that can be told what to charge.
 */
class StoreCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest checkout is supported by design (README Feature 4) — no forced
        // account creation. A logged-in customer is attached in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],

            'currency' => ['required', Rule::in(['GHS', 'USD'])],
            'delivery_method' => ['required', Rule::in(['standard', 'express'])],

            'shipping_address' => ['required', 'array'],
            'shipping_address.full_name' => ['required', 'string', 'max:255'],
            'shipping_address.email' => ['required', 'email', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:32'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.region' => ['nullable', 'string', 'max:120'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:32'],
            // Required, and not merely nullable: country is what routes the
            // order to Yango or DHL, so an absent one is a validation error
            // before payment rather than an unpriced order (Feature 5 edge case).
            'shipping_address.country' => ['required', 'string', 'size:2'],
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
