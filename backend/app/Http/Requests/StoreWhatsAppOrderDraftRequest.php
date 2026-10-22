<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCheckoutItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Order on WhatsApp" from the storefront: the cart, by the same lines the
 * checkout takes (inventory item id, or slug + size + colour). No prices, no
 * address, no contact details: those are settled in the chat.
 */
class StoreWhatsAppOrderDraftRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'currency' => ['required', Rule::in(['GHS', 'USD'])],
        ];
    }
}
