<?php

namespace App\Http\Requests;

use App\Services\GiftCards\GiftCardService;
use App\Support\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Buying a gift card. The amount must be one the client has set in admin, so
 * the storefront can't be told to sell a card for any figure it likes; the
 * price itself is the amount, converted at the locked rate for USD.
 */
class StoreGiftCardPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $purchaser = $this->input('purchaser');

        if (is_array($purchaser) && filled($purchaser['first_name'] ?? null)) {
            $purchaser['full_name'] = PersonName::join($purchaser['first_name'], $purchaser['last_name'] ?? null);
            $this->merge(['purchaser' => $purchaser]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', Rule::in(app(GiftCardService::class)->settings()['amounts'])],
            'currency' => ['required', Rule::in(['GHS', 'USD'])],
            'purchaser.first_name' => ['required', 'string', 'max:120'],
            'purchaser.last_name' => ['nullable', 'string', 'max:120'],
            'purchaser.full_name' => ['required', 'string', 'max:255'],
            'purchaser.email' => ['required', 'email', 'max:255'],
            'purchaser.phone' => ['nullable', 'string', 'max:32'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.in' => 'Choose one of the gift card amounts shown.',
        ];
    }
}
